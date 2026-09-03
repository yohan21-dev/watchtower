<?php

namespace Sti\Cctv;

/**
 * Session-based authentication and role helpers.
 *
 * Roles:
 *   super_admin -> full system control: manage admins, all NVRs/cameras,
 *                  all permissions, view audit log
 *   admin       -> manage NVRs/cameras (CRUD) and grant/revoke viewer
 *                  access; cannot manage other admins or super_admins
 *   viewer      -> end user; can only open NVRs/cameras explicitly granted
 *                  to them, or ones flagged "public"
 */
class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => (($_SERVER['HTTPS'] ?? '') !== ''), // true once served over HTTPS
            ]);
            session_start();
        }
    }

    public static function attempt(string $usernameOrEmail, string $password): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1');
        $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
        $user = $stmt->fetch();

        $hash = $user['password_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvali';
        $ok = password_verify($password, $hash);

        self::audit($user['id'] ?? null, $ok ? 'login.success' : 'login.failed', ['username' => $usernameOrEmail]);

        if (!$user || !$ok) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'         => (int) $user['id'],
            'username'   => $user['username'],
            'full_name'  => $user['full_name'],
            'role'       => $user['role'],
            'department' => $user['department'],
        ];
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        return $_SESSION['user']['id'] ?? null;
    }

    public static function role(): ?string
    {
        return $_SESSION['user']['role'] ?? null;
    }

    public static function isPrivileged(): bool
    {
        return in_array(self::role(), ['admin', 'super_admin'], true);
    }

    /** Redirects to login.php if not authenticated. Call at the top of protected pages. */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: login.php');
            exit;
        }
    }

    /** Redirects to dashboard with an error flag if the current user lacks $roles. */
    public static function requireRole(array $roles): void
    {
        self::requireLogin();
        if (!in_array(self::role(), $roles, true)) {
            header('Location: dashboard.php?error=forbidden');
            exit;
        }
    }

    public static function audit(?int $userId, string $action, array $details = []): void
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'INSERT INTO audit_log (user_id, action, ip_address, details) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $action, $_SERVER['REMOTE_ADDR'] ?? null, json_encode($details)]);
        } catch (\Throwable $e) {
            // Auditing must never break the app.
        }
    }

    /** Basic CSRF token helpers for the HTML forms. */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

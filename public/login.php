<?php
require __DIR__ . '/bootstrap.php';

use Sti\Cctv\Auth;

if (Auth::check()) {
    header('Location: dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        if (Auth::attempt($username, $password)) {
            header('Location: dashboard.php');
            exit;
        }
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign in · STI CCTV Portal</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <div class="brand"><span class="mark">STI</span> CCTV Portal</div>
        <p class="tagline">Live camera access for authorized personnel</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if (($_GET['logged_out'] ?? '') === '1'): ?>
            <div class="alert alert-success">You have been signed out.</div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
            <div class="field">
                <label for="username">Username or email</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Sign in</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>

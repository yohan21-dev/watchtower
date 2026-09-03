<?php
require __DIR__ . '/../bootstrap.php';

use Sti\Cctv\Auth;
use Sti\Cctv\Database;

Auth::requireRole(['admin', 'super_admin']);
$user = Auth::user();
$pdo = Database::connection();
$notice = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired, please retry.';
    } else {
        $action = $_POST['action'] ?? '';
        $requestedRole = $_POST['role'] ?? 'viewer';

        // Admins (non-super) may only create/edit viewer accounts.
        if ($user['role'] === 'admin' && $requestedRole !== 'viewer') {
            $error = 'Only a super admin can assign admin or super admin roles.';
        } elseif ($action === 'create') {
            $stmt = $pdo->prepare(
                'INSERT INTO users (username, email, password_hash, full_name, department, role) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                trim($_POST['username']),
                trim($_POST['email']),
                password_hash($_POST['password'], PASSWORD_BCRYPT),
                trim($_POST['full_name']),
                trim($_POST['department']) ?: null,
                $requestedRole,
            ]);
            Auth::audit($user['id'], 'user.create', ['username' => $_POST['username']]);
            $notice = 'User account created.';
        } elseif ($action === 'update') {
            $id = (int) $_POST['id'];
            $sql = 'UPDATE users SET email=?, full_name=?, department=?, role=?, is_active=?';
            $params = [
                trim($_POST['email']),
                trim($_POST['full_name']),
                trim($_POST['department']) ?: null,
                $requestedRole,
                isset($_POST['is_active']) ? 1 : 0,
            ];
            if (!empty($_POST['password'])) {
                $sql .= ', password_hash=?';
                $params[] = password_hash($_POST['password'], PASSWORD_BCRYPT);
            }
            $sql .= ' WHERE id=?';
            $params[] = $id;
            $pdo->prepare($sql)->execute($params);
            Auth::audit($user['id'], 'user.update', ['id' => $id]);
            $notice = 'User updated.';
        } elseif ($action === 'delete') {
            if ($user['role'] !== 'super_admin') {
                $error = 'Only a super admin can delete accounts.';
            } else {
                $id = (int) $_POST['id'];
                if ($id === $user['id']) {
                    $error = 'You cannot delete your own account.';
                } else {
                    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
                    Auth::audit($user['id'], 'user.delete', ['id' => $id]);
                    $notice = 'User deleted.';
                }
            }
        }
    }
}

$users = $pdo->query('SELECT * FROM users ORDER BY full_name')->fetchAll();
$editing = null;
if (!empty($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

include __DIR__ . '/_header.php';
?>

<?php if ($notice): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
    <h3><?= $editing ? 'Edit user' : 'Add a new user' ?></h3>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>

        <div class="form-grid">
            <?php if (!$editing): ?>
            <div class="field">
                <label>Username</label>
                <input type="text" name="username" required value="<?= e($editing['username'] ?? '') ?>">
            </div>
            <?php endif; ?>
            <div class="field">
                <label>Full name</label>
                <input type="text" name="full_name" required value="<?= e($editing['full_name'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Email</label>
                <input type="email" name="email" required value="<?= e($editing['email'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Department (optional)</label>
                <input type="text" name="department" value="<?= e($editing['department'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Role</label>
                <select name="role" <?= $user['role'] === 'admin' ? 'disabled' : '' ?>>
                    <option value="viewer" <?= (($editing['role'] ?? 'viewer') === 'viewer') ? 'selected' : '' ?>>Viewer</option>
                    <?php if ($user['role'] === 'super_admin'): ?>
                        <option value="admin" <?= (($editing['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option>
                        <option value="super_admin" <?= (($editing['role'] ?? '') === 'super_admin') ? 'selected' : '' ?>>Super Admin</option>
                    <?php endif; ?>
                </select>
                <?php if ($user['role'] === 'admin'): ?>
                    <input type="hidden" name="role" value="viewer">
                    <div class="muted" style="margin-top:0.3rem;">Admins can only manage viewer accounts.</div>
                <?php endif; ?>
            </div>
            <div class="field">
                <label>Password <?= $editing ? '(leave blank to keep current)' : '' ?></label>
                <input type="password" name="password" autocomplete="new-password" <?= $editing ? '' : 'required' ?>>
            </div>
        </div>

        <?php if ($editing): ?>
        <div class="checkbox-row" style="margin-top: 0.9rem;">
            <input type="checkbox" id="is_active" name="is_active" <?= !empty($editing['is_active']) ? 'checked' : '' ?>>
            <label for="is_active" style="margin:0;">Account active</label>
        </div>
        <?php endif; ?>

        <div class="form-actions" style="display:flex; gap:0.6rem;">
            <button type="submit" class="btn btn-primary" style="width:auto;"><?= $editing ? 'Save changes' : 'Create user' ?></button>
            <?php if ($editing): ?><a href="users.php" class="btn btn-secondary" style="width:auto;">Cancel</a><?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <h3>Accounts</h3>
    <table>
        <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Department</th><th>Role</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= e($u['full_name']) ?></td>
                <td><?= e($u['username']) ?></td>
                <td><?= e($u['email']) ?></td>
                <td><?= e($u['department'] ?? '—') ?></td>
                <td><span class="badge badge-<?= e($u['role']) ?>"><?= e($u['role']) ?></span></td>
                <td><?= $u['is_active'] ? 'Active' : 'Disabled' ?></td>
                <td class="row-actions">
                    <a class="btn btn-secondary btn-small" href="users.php?edit=<?= (int) $u['id'] ?>">Edit</a>
                    <a class="btn btn-secondary btn-small" href="permissions.php?user_id=<?= (int) $u['id'] ?>">Access</a>
                    <?php if ($user['role'] === 'super_admin' && $u['id'] != $user['id']): ?>
                    <form method="post" onsubmit="return confirm('Delete this account?');" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-small">Delete</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/_footer.php'; ?>

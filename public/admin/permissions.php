<?php
require __DIR__ . '/../bootstrap.php';

use Sti\Cctv\Auth;
use Sti\Cctv\Database;

Auth::requireRole(['admin', 'super_admin']);
$user = Auth::user();
$pdo = Database::connection();
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        $action = $_POST['action'] ?? '';
        if ($action === 'grant') {
            $stmt = $pdo->prepare(
                'INSERT IGNORE INTO permissions (user_id, scope_type, scope_id, granted_by) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([(int) $_POST['user_id'], $_POST['scope_type'], (int) $_POST['scope_id'], $user['id']]);
            Auth::audit($user['id'], 'permission.grant', $_POST);
            $notice = 'Access granted.';
        } elseif ($action === 'revoke') {
            $pdo->prepare('DELETE FROM permissions WHERE id = ?')->execute([(int) $_POST['id']]);
            Auth::audit($user['id'], 'permission.revoke', ['id' => $_POST['id']]);
            $notice = 'Access revoked.';
        }
    }
}

$viewers = $pdo->query("SELECT id, full_name, username, department FROM users WHERE role = 'viewer' ORDER BY full_name")->fetchAll();
$selectedUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : ($viewers[0]['id'] ?? null);

$nvrs = $pdo->query('SELECT id, name FROM nvrs ORDER BY name')->fetchAll();
$cameras = $pdo->query('SELECT id, name, nvr_id FROM cameras ORDER BY name')->fetchAll();

$grants = [];
if ($selectedUserId) {
    $stmt = $pdo->prepare('SELECT * FROM permissions WHERE user_id = ?');
    $stmt->execute([$selectedUserId]);
    foreach ($stmt->fetchAll() as $g) {
        $grants[$g['scope_type']][$g['scope_id']] = $g['id'];
    }
}

include __DIR__ . '/_header.php';
?>

<?php if ($notice): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif; ?>

<div class="card">
    <h3>Choose a viewer</h3>
    <?php if (empty($viewers)): ?>
        <p class="muted">No viewer accounts yet. <a href="users.php">Create one</a> first.</p>
    <?php else: ?>
        <form method="get" class="field" style="max-width:360px;">
            <label>Viewer account</label>
            <select name="user_id" onchange="this.form.submit()">
                <?php foreach ($viewers as $v): ?>
                    <option value="<?= (int) $v['id'] ?>" <?= $v['id'] == $selectedUserId ? 'selected' : '' ?>>
                        <?= e($v['full_name']) ?> (<?= e($v['username']) ?>)<?= $v['department'] ? ' — ' . e($v['department']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    <?php endif; ?>
</div>

<?php if ($selectedUserId): ?>
<div class="card">
    <h3>NVR access</h3>
    <p class="muted">Granting an NVR gives access to every camera under it automatically. NVRs/cameras marked "public" in their own settings are always visible and don't need a grant here.</p>
    <table>
        <thead><tr><th>NVR</th><th>Access</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($nvrs as $nvr): $granted = isset($grants['nvr'][$nvr['id']]); ?>
            <tr>
                <td><?= e($nvr['name']) ?></td>
                <td><?= $granted ? '<span class="badge badge-admin">Granted</span>' : '<span class="muted">Not granted</span>' ?></td>
                <td>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
                        <input type="hidden" name="user_id" value="<?= (int) $selectedUserId ?>">
                        <input type="hidden" name="scope_type" value="nvr">
                        <input type="hidden" name="scope_id" value="<?= (int) $nvr['id'] ?>">
                        <?php if ($granted): ?>
                            <input type="hidden" name="action" value="revoke">
                            <input type="hidden" name="id" value="<?= (int) $grants['nvr'][$nvr['id']] ?>">
                            <button type="submit" class="btn btn-secondary btn-small">Revoke</button>
                        <?php else: ?>
                            <input type="hidden" name="action" value="grant">
                            <button type="submit" class="btn btn-primary btn-small">Grant</button>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3>Individual camera access</h3>
    <p class="muted">Use this for one-off exceptions, e.g. one camera on an NVR the viewer otherwise can't see.</p>
    <table>
        <thead><tr><th>Camera</th><th>NVR</th><th>Access</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($cameras as $cam):
            $granted = isset($grants['camera'][$cam['id']]);
            $nvrName = current(array_filter($nvrs, fn($n) => $n['id'] == $cam['nvr_id']))['name'] ?? '';
        ?>
            <tr>
                <td><?= e($cam['name']) ?></td>
                <td><?= e($nvrName) ?></td>
                <td><?= $granted ? '<span class="badge badge-admin">Granted</span>' : '<span class="muted">Not granted</span>' ?></td>
                <td>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
                        <input type="hidden" name="user_id" value="<?= (int) $selectedUserId ?>">
                        <input type="hidden" name="scope_type" value="camera">
                        <input type="hidden" name="scope_id" value="<?= (int) $cam['id'] ?>">
                        <?php if ($granted): ?>
                            <input type="hidden" name="action" value="revoke">
                            <input type="hidden" name="id" value="<?= (int) $grants['camera'][$cam['id']] ?>">
                            <button type="submit" class="btn btn-secondary btn-small">Revoke</button>
                        <?php else: ?>
                            <input type="hidden" name="action" value="grant">
                            <button type="submit" class="btn btn-primary btn-small">Grant</button>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/_footer.php'; ?>

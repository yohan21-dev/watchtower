<?php
require __DIR__ . '/../bootstrap.php';

use Sti\Cctv\Auth;
use Sti\Cctv\Crypto;
use Sti\Cctv\Database;

Auth::requireRole(['admin', 'super_admin']);
$user = Auth::user();
$pdo = Database::connection();
$notice = null;
$error = null;

// ---- Handle actions ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired, please retry.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $stmt = $pdo->prepare(
                'INSERT INTO nvrs (name, location, ip_address, http_port, web_url, admin_username, admin_password, is_public, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                trim($_POST['name']),
                trim($_POST['location']) ?: null,
                trim($_POST['ip_address']),
                (int) ($_POST['http_port'] ?: 80),
                trim($_POST['web_url']) ?: null,
                trim($_POST['admin_username']) ?: null,
                !empty($_POST['admin_password']) ? Crypto::encrypt($_POST['admin_password']) : null,
                isset($_POST['is_public']) ? 1 : 0,
                $user['id'],
            ]);
            Auth::audit($user['id'], 'nvr.create', ['name' => $_POST['name']]);
            $notice = 'NVR added.';
        } elseif ($action === 'update') {
            $id = (int) $_POST['id'];
            $sql = 'UPDATE nvrs SET name=?, location=?, ip_address=?, http_port=?, web_url=?, admin_username=?, is_public=?';
            $params = [
                trim($_POST['name']),
                trim($_POST['location']) ?: null,
                trim($_POST['ip_address']),
                (int) ($_POST['http_port'] ?: 80),
                trim($_POST['web_url']) ?: null,
                trim($_POST['admin_username']) ?: null,
                isset($_POST['is_public']) ? 1 : 0,
            ];
            if (!empty($_POST['admin_password'])) {
                $sql .= ', admin_password=?';
                $params[] = Crypto::encrypt($_POST['admin_password']);
            }
            $sql .= ' WHERE id=?';
            $params[] = $id;
            $pdo->prepare($sql)->execute($params);
            Auth::audit($user['id'], 'nvr.update', ['id' => $id]);
            $notice = 'NVR updated.';
        } elseif ($action === 'delete') {
            $id = (int) $_POST['id'];
            $pdo->prepare('DELETE FROM nvrs WHERE id = ?')->execute([$id]);
            Auth::audit($user['id'], 'nvr.delete', ['id' => $id]);
            $notice = 'NVR and its cameras were deleted.';
        }
    }
}

$nvrs = $pdo->query('SELECT * FROM nvrs ORDER BY name')->fetchAll();
$editing = null;
if (!empty($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM nvrs WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

include __DIR__ . '/_header.php';
?>

<?php if ($notice): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
    <h3><?= $editing ? 'Edit NVR' : 'Add a new NVR' ?></h3>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>

        <div class="form-grid">
            <div class="field">
                <label>Name</label>
                <input type="text" name="name" required placeholder="e.g. NVR 1" value="<?= e($editing['name'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Location</label>
                <input type="text" name="location" placeholder="e.g. Main Building, Server Room" value="<?= e($editing['location'] ?? '') ?>">
            </div>
            <div class="field">
                <label>IP address</label>
                <input type="text" name="ip_address" required placeholder="192.168.1.10" value="<?= e($editing['ip_address'] ?? '') ?>">
            </div>
            <div class="field">
                <label>HTTP port</label>
                <input type="number" name="http_port" value="<?= e((string) ($editing['http_port'] ?? 80)) ?>">
            </div>
            <div class="field">
                <label>Web URL override (optional)</label>
                <input type="text" name="web_url" placeholder="Leave blank to auto-build from IP:port" value="<?= e($editing['web_url'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Device admin username (optional)</label>
                <input type="text" name="admin_username" value="<?= e($editing['admin_username'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Device admin password <?= $editing ? '(leave blank to keep current)' : '(optional)' ?></label>
                <input type="password" name="admin_password" autocomplete="new-password">
            </div>
        </div>

        <div class="checkbox-row" style="margin-top: 0.9rem;">
            <input type="checkbox" id="is_public" name="is_public" <?= !empty($editing['is_public']) ? 'checked' : '' ?>>
            <label for="is_public" style="margin:0;">Make visible to all viewers (public access)</label>
        </div>

        <div class="form-actions" style="display:flex; gap:0.6rem;">
            <button type="submit" class="btn btn-primary" style="width:auto;"><?= $editing ? 'Save changes' : 'Add NVR' ?></button>
            <?php if ($editing): ?><a href="nvrs.php" class="btn btn-secondary" style="width:auto;">Cancel</a><?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <h3>Configured NVRs</h3>
    <table>
        <thead>
            <tr><th>Name</th><th>Location</th><th>IP : Port</th><th>Visibility</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($nvrs as $nvr): ?>
            <tr>
                <td><?= e($nvr['name']) ?></td>
                <td><?= e($nvr['location'] ?? '—') ?></td>
                <td><?= e($nvr['ip_address']) ?>:<?= e((string) $nvr['http_port']) ?></td>
                <td><?= $nvr['is_public'] ? 'Public' : 'Restricted' ?></td>
                <td>
                    <span class="status-dot" data-status="<?= e($nvr['status']) ?>"></span>
                    <span class="status-label" data-status="<?= e($nvr['status']) ?>"><?= e($nvr['status']) ?></span>
                </td>
                <td class="row-actions">
                    <a class="btn btn-secondary btn-small" href="nvrs.php?edit=<?= (int) $nvr['id'] ?>">Edit</a>
                    <a class="btn btn-secondary btn-small" href="cameras.php?nvr_id=<?= (int) $nvr['id'] ?>">Cameras</a>
                    <form method="post" onsubmit="return confirm('Delete this NVR and all of its cameras? This cannot be undone.');" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $nvr['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-small">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($nvrs)): ?>
            <tr><td colspan="6" class="muted">No NVRs configured yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/_footer.php'; ?>

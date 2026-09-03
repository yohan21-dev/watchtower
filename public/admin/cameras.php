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

        if ($action === 'create') {
            $stmt = $pdo->prepare(
                'INSERT INTO cameras (nvr_id, name, channel_no, ip_address, http_port, web_url, is_public)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                (int) $_POST['nvr_id'],
                trim($_POST['name']),
                $_POST['channel_no'] !== '' ? (int) $_POST['channel_no'] : null,
                trim($_POST['ip_address']),
                (int) ($_POST['http_port'] ?: 80),
                trim($_POST['web_url']) ?: null,
                isset($_POST['is_public']) ? 1 : 0,
            ]);
            Auth::audit($user['id'], 'camera.create', ['name' => $_POST['name']]);
            $notice = 'Camera added.';
        } elseif ($action === 'update') {
            $id = (int) $_POST['id'];
            $pdo->prepare(
                'UPDATE cameras SET nvr_id=?, name=?, channel_no=?, ip_address=?, http_port=?, web_url=?, is_public=? WHERE id=?'
            )->execute([
                (int) $_POST['nvr_id'],
                trim($_POST['name']),
                $_POST['channel_no'] !== '' ? (int) $_POST['channel_no'] : null,
                trim($_POST['ip_address']),
                (int) ($_POST['http_port'] ?: 80),
                trim($_POST['web_url']) ?: null,
                isset($_POST['is_public']) ? 1 : 0,
                $id,
            ]);
            Auth::audit($user['id'], 'camera.update', ['id' => $id]);
            $notice = 'Camera updated.';
        } elseif ($action === 'delete') {
            $id = (int) $_POST['id'];
            $pdo->prepare('DELETE FROM cameras WHERE id = ?')->execute([$id]);
            Auth::audit($user['id'], 'camera.delete', ['id' => $id]);
            $notice = 'Camera deleted.';
        }
    }
}

$nvrs = $pdo->query('SELECT id, name FROM nvrs ORDER BY name')->fetchAll();
$filterNvrId = isset($_GET['nvr_id']) ? (int) $_GET['nvr_id'] : null;

$sql = 'SELECT c.*, n.name AS nvr_name FROM cameras c JOIN nvrs n ON n.id = c.nvr_id';
$params = [];
if ($filterNvrId) {
    $sql .= ' WHERE c.nvr_id = ?';
    $params[] = $filterNvrId;
}
$sql .= ' ORDER BY n.name, c.name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cameras = $stmt->fetchAll();

$editing = null;
if (!empty($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM cameras WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

include __DIR__ . '/_header.php';
?>

<?php if ($notice): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if (empty($nvrs)): ?>
    <div class="alert alert-info">Add an NVR first before configuring cameras. <a href="nvrs.php">Go to NVRs</a></div>
<?php else: ?>

<div class="card">
    <h3><?= $editing ? 'Edit camera' : 'Add a new camera' ?></h3>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>

        <div class="form-grid">
            <div class="field">
                <label>Parent NVR</label>
                <select name="nvr_id" required>
                    <?php foreach ($nvrs as $nvr): ?>
                        <option value="<?= (int) $nvr['id'] ?>"
                            <?= (($editing['nvr_id'] ?? $filterNvrId) == $nvr['id']) ? 'selected' : '' ?>>
                            <?= e($nvr['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Camera name</label>
                <input type="text" name="name" required placeholder="e.g. Computer Lab 101" value="<?= e($editing['name'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Channel # (optional)</label>
                <input type="number" name="channel_no" value="<?= e((string) ($editing['channel_no'] ?? '')) ?>">
            </div>
            <div class="field">
                <label>IP address</label>
                <input type="text" name="ip_address" required placeholder="192.168.1.11" value="<?= e($editing['ip_address'] ?? '') ?>">
            </div>
            <div class="field">
                <label>HTTP port</label>
                <input type="number" name="http_port" value="<?= e((string) ($editing['http_port'] ?? 80)) ?>">
            </div>
            <div class="field">
                <label>Web URL override (optional)</label>
                <input type="text" name="web_url" placeholder="Leave blank to auto-build from IP:port" value="<?= e($editing['web_url'] ?? '') ?>">
            </div>
        </div>

        <div class="checkbox-row" style="margin-top: 0.9rem;">
            <input type="checkbox" id="is_public" name="is_public" <?= !empty($editing['is_public']) ? 'checked' : '' ?>>
            <label for="is_public" style="margin:0;">Make visible to all viewers (public access)</label>
        </div>

        <div class="form-actions" style="display:flex; gap:0.6rem;">
            <button type="submit" class="btn btn-primary" style="width:auto;"><?= $editing ? 'Save changes' : 'Add camera' ?></button>
            <?php if ($editing): ?><a href="cameras.php" class="btn btn-secondary" style="width:auto;">Cancel</a><?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <h3>Configured cameras <?php if ($filterNvrId): $n = array_filter($nvrs, fn($x) => $x['id'] == $filterNvrId); ?>
        &middot; <?= e(reset($n)['name'] ?? '') ?> <a href="cameras.php" class="muted">(clear filter)</a>
    <?php endif; ?></h3>
    <table>
        <thead>
            <tr><th>Name</th><th>NVR</th><th>Channel</th><th>IP : Port</th><th>Visibility</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($cameras as $cam): ?>
            <tr>
                <td><?= e($cam['name']) ?></td>
                <td><?= e($cam['nvr_name']) ?></td>
                <td><?= e((string) ($cam['channel_no'] ?? '—')) ?></td>
                <td><?= e($cam['ip_address']) ?>:<?= e((string) $cam['http_port']) ?></td>
                <td><?= $cam['is_public'] ? 'Public' : 'Restricted' ?></td>
                <td>
                    <span class="status-dot" data-status="<?= e($cam['status']) ?>"></span>
                    <span class="status-label" data-status="<?= e($cam['status']) ?>"><?= e($cam['status']) ?></span>
                </td>
                <td class="row-actions">
                    <a class="btn btn-secondary btn-small" href="cameras.php?edit=<?= (int) $cam['id'] ?>">Edit</a>
                    <form method="post" onsubmit="return confirm('Delete this camera?');" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $cam['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-small">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($cameras)): ?>
            <tr><td colspan="7" class="muted">No cameras configured yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/_footer.php'; ?>

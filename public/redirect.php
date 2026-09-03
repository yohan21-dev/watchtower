<?php
require __DIR__ . '/bootstrap.php';

use Sti\Cctv\Auth;
use Sti\Cctv\CctvRepository;
use Sti\Cctv\Database;

Auth::requireLogin();

$type = $_GET['type'] ?? '';
$id = (int) ($_GET['id'] ?? 0);
$user = Auth::user();

if (!in_array($type, ['nvr', 'camera'], true) || $id <= 0) {
    http_response_code(400);
    exit('Invalid request.');
}

$allowed = $type === 'nvr'
    ? CctvRepository::userCanAccessNvr($user, $id)
    : CctvRepository::userCanAccessCamera($user, $id);

if (!$allowed) {
    Auth::audit($user['id'], 'access.denied', ['type' => $type, 'id' => $id]);
    http_response_code(403);
    exit('You do not have access to this device.');
}

$table = $type === 'nvr' ? 'nvrs' : 'cameras';
$pdo = Database::connection();
$stmt = $pdo->prepare("SELECT ip_address, http_port, web_url FROM {$table} WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    exit('Device not found.');
}

Auth::audit($user['id'], "{$type}.view", ['id' => $id]);

$url = $row['web_url'] ?: "http://{$row['ip_address']}:{$row['http_port']}";

// Open the device's own Hikvision web UI in a new tab rather than a hard
// redirect, so the user never loses their place in the portal.
header('Location: ' . $url);
exit;

<?php
require __DIR__ . '/bootstrap.php';

use Sti\Cctv\Auth;
use Sti\Cctv\CctvRepository;
use Sti\Cctv\Config;

Auth::requireLogin();
$user = Auth::user();
$tree = CctvRepository::visibleTree($user);
$wsUrl = Config::get('WS_PUBLIC_URL', 'ws://localhost:8080');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard · STI CCTV Portal</title>
<link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
<script>const STI_WS_URL = <?= json_encode($wsUrl) ?>;</script>
</head>
<body>

<div class="topbar">
    <div class="brand">
        <img src="assets/img/sti-cubao-logo.png" alt="STI" class="brand-logo">
        <span>CCTV Portal<br><small>Hikvision camera access</small></span>
    </div>
    <div class="topbar-right">
        <span class="pill"><?= e($user['full_name']) ?></span>
        <span class="role-badge"><?= e($user['role']) ?></span>
        <?php if (Auth::isPrivileged()): ?>
            <a href="admin/index.php" class="btn btn-outline btn-small">Admin panel</a>
        <?php endif; ?>
        <a href="logout.php" class="btn btn-outline btn-small">Sign out</a>
    </div>
</div>

<div class="page">
    <div class="page-header">
        <div>
            <h1>Camera access</h1>
            <p>Click any NVR or camera to open its live view in a new tab.</p>
        </div>
        <div class="ws-indicator">
            <span class="ws-dot" id="wsDot"></span>
            <span id="wsText">Connecting…</span>
        </div>
    </div>

    <?php if (($_GET['error'] ?? '') === 'forbidden'): ?>
        <div class="alert alert-error">You don't have permission to view that page.</div>
    <?php endif; ?>

    <?php if (empty($tree)): ?>
        <div class="card">
            <p class="muted">No cameras have been made available to your account yet. Contact your administrator to request access.</p>
        </div>
    <?php else: ?>
        <div class="nvr-grid">
            <?php foreach ($tree as $nvr): ?>
                <div class="nvr-card">
                    <div class="nvr-card-header">
                        <div>
                            <h2><?= e($nvr['name']) ?></h2>
                            <?php if (!empty($nvr['location'])): ?>
                                <div class="loc"><?= e($nvr['location']) ?></div>
                            <?php endif; ?>
                        </div>
                        <a href="redirect.php?type=nvr&id=<?= (int) $nvr['id'] ?>" target="_blank" rel="noopener"
                           title="Open NVR web interface" style="display:flex;align-items:center;gap:0.5rem;">
                            <span class="status-dot" data-status="<?= e($nvr['status']) ?>"
                                  data-status-dot="nvr:<?= (int) $nvr['id'] ?>"></span>
                            <span class="status-label" data-status="<?= e($nvr['status']) ?>"
                                  data-status-label="nvr:<?= (int) $nvr['id'] ?>"><?= e($nvr['status']) ?></span>
                        </a>
                    </div>

                    <?php if (empty($nvr['cameras'])): ?>
                        <div class="empty-note">No cameras configured under this NVR.</div>
                    <?php else: ?>
                        <ul class="camera-list">
                            <?php foreach ($nvr['cameras'] as $cam): ?>
                                <li class="camera-item"
                                    onclick="window.open('redirect.php?type=camera&id=<?= (int) $cam['id'] ?>', '_blank')">
                                    <div class="camera-info">
                                        <span class="status-dot" data-status="<?= e($cam['status']) ?>"
                                              data-status-dot="camera:<?= (int) $cam['id'] ?>"></span>
                                        <div>
                                            <div class="camera-name"><?= e($cam['name']) ?></div>
                                            <div class="camera-ip"><?= e($cam['ip_address']) ?></div>
                                        </div>
                                    </div>
                                    <span class="status-label" data-status="<?= e($cam['status']) ?>"
                                          data-status-label="camera:<?= (int) $cam['id'] ?>"><?= e($cam['status']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<footer class="app-footer">STI CCTV Portal &middot; Internal use only</footer>

<script src="assets/js/ws-client.js"></script>
</body>
</html>

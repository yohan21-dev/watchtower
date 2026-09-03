<?php
/** @var array $user */
$currentPage = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin · STI CCTV Portal</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="topbar">
    <div class="brand">
        <span class="mark">STI</span>
        <span>CCTV Portal<br><small>Admin panel</small></span>
    </div>
    <div class="topbar-right">
        <span class="pill"><?= e($user['full_name']) ?></span>
        <span class="role-badge"><?= e($user['role']) ?></span>
        <a href="../dashboard.php" class="btn btn-outline btn-small">Back to dashboard</a>
        <a href="../logout.php" class="btn btn-outline btn-small">Sign out</a>
    </div>
</div>

<div class="page">
    <div class="page-header">
        <div>
            <h1>Admin panel</h1>
            <p>Manage NVRs, cameras, users, and access.</p>
        </div>
    </div>

    <div class="tabs">
        <a class="tab <?= $currentPage === 'nvrs.php' ? 'active' : '' ?>" href="nvrs.php">NVRs</a>
        <a class="tab <?= $currentPage === 'cameras.php' ? 'active' : '' ?>" href="cameras.php">Cameras</a>
        <a class="tab <?= $currentPage === 'permissions.php' ? 'active' : '' ?>" href="permissions.php">Access &amp; permissions</a>
        <?php if ($user['role'] === 'super_admin' || $user['role'] === 'admin'): ?>
            <a class="tab <?= $currentPage === 'users.php' ? 'active' : '' ?>" href="users.php">Users</a>
        <?php endif; ?>
    </div>

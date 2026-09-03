<?php
require __DIR__ . '/../bootstrap.php';
use Sti\Cctv\Auth;
Auth::requireRole(['admin', 'super_admin']);
header('Location: nvrs.php');
exit;

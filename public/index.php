<?php
require __DIR__ . '/bootstrap.php';

use Sti\Cctv\Auth;

header('Location: ' . (Auth::check() ? 'dashboard.php' : 'login.php'));
exit;

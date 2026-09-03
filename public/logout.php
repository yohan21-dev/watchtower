<?php
require __DIR__ . '/bootstrap.php';

use Sti\Cctv\Auth;

if (Auth::check()) {
    Auth::audit(Auth::id(), 'logout');
}
Auth::logout();
header('Location: login.php?logged_out=1');
exit;

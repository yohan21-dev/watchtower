<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Sti\Cctv\Auth;
use Sti\Cctv\Config;

Config::load();
Auth::start();

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

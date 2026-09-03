<?php

namespace Sti\Cctv;

use Dotenv\Dotenv;

class Config
{
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        $root = dirname(__DIR__);
        if (file_exists($root . '/.env')) {
            $dotenv = Dotenv::createImmutable($root);
            $dotenv->load();
        }
        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        $value = $_ENV[$key] ?? getenv($key);
        return $value === false || $value === null ? $default : $value;
    }
}
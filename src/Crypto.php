<?php

namespace Sti\Cctv;


/**
 * Encrypts/decrypts sensitive fields (e.g. NVR device passwords) before they
 * are stored in the database, using AES-256-CBC with a random IV per value.
 */
class Crypto
{
    public static function encrypt(?string $plaintext): ?string
    {
        if ($plaintext === null || $plaintext === '') {
            return null;
        }
        $key = self::key();
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $cipher);
    }

    public static function decrypt(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }
        $key = self::key();
        $raw = base64_decode($stored);
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $plain = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return $plain === false ? null : $plain;
    }

    private static function key(): string
    {
        $b64 = Config::get('APP_ENCRYPTION_KEY');
        $key = base64_decode((string) $b64, true);
        if (!$key || strlen($key) < 32) {
            throw new \RuntimeException('APP_ENCRYPTION_KEY must be a base64 32-byte key. See .env.example.');
        }
        return $key;
    }
}

<?php

namespace Sti\Cctv;

use PDO;

/**
 * Determines whether an NVR/camera is reachable.
 *
 * Approach: a lightweight TCP connect() to the device's HTTP (or RTSP) port,
 * with a short timeout. This does NOT require Hikvision SDK credentials and
 * works for any device that exposes a management/stream port on the LAN.
 * It answers "is something listening / is the device up", which is a good
 * proxy for CCTV availability without pulling a live frame.
 *
 * For a stronger check you can extend this to call Hikvision's ISAPI
 * endpoint (e.g. GET /ISAPI/System/status) with the stored device
 * credentials and inspect the HTTP response instead of just the socket.
 */
class CameraStatusService
{
    private const TIMEOUT_SECONDS = 2;

    public static function isReachable(string $ip, int $port): bool
    {
        $conn = @fsockopen($ip, $port, $errno, $errstr, self::TIMEOUT_SECONDS);
        if ($conn) {
            fclose($conn);
            return true;
        }
        return false;
    }

    /**
     * Checks every NVR and camera in the DB, updates their status column,
     * and returns a flat list of {type, id, status} changes for broadcasting.
     */
    public static function checkAll(): array
    {
        $pdo = Database::connection();
        $updates = [];

        foreach (['nvrs', 'cameras'] as $table) {
            $type = $table === 'nvrs' ? 'nvr' : 'camera';
            $rows = $pdo->query("SELECT id, ip_address, http_port, status FROM {$table}")->fetchAll();

            foreach ($rows as $row) {
                $online = self::isReachable($row['ip_address'], (int) $row['http_port']);
                $newStatus = $online ? 'online' : 'offline';

                if ($newStatus !== $row['status']) {
                    $stmt = $pdo->prepare("UPDATE {$table} SET status = ?, last_checked_at = NOW() WHERE id = ?");
                    $stmt->execute([$newStatus, $row['id']]);
                } else {
                    $stmt = $pdo->prepare("UPDATE {$table} SET last_checked_at = NOW() WHERE id = ?");
                    $stmt->execute([$row['id']]);
                }

                $updates[] = ['type' => $type, 'id' => (int) $row['id'], 'status' => $newStatus];
            }
        }

        return $updates;
    }
}

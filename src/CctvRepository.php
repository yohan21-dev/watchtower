<?php

namespace Sti\Cctv;

class CctvRepository
{
    /**
     * Returns NVRs (each with a 'cameras' array) visible to the given user.
     * Privileged roles (admin/super_admin) see everything; viewers see only
     * NVRs/cameras flagged public, or explicitly granted via `permissions`.
     */
    public static function visibleTree(array $user): array
    {
        $pdo = Database::connection();
        $privileged = in_array($user['role'], ['admin', 'super_admin'], true);

        if ($privileged) {
            $nvrs = $pdo->query(
                'SELECT id, name, location, ip_address, http_port, is_public, status, last_checked_at FROM nvrs ORDER BY name'
            )->fetchAll();
            $cameras = $pdo->query(
                'SELECT id, nvr_id, name, channel_no, ip_address, http_port, is_public, status, last_checked_at FROM cameras ORDER BY name'
            )->fetchAll();
        } else {
            $userId = $user['id'];

            // Cameras visible either because they're public, individually
            // granted, or their parent NVR is granted/public.
            $stmt = $pdo->prepare(
                'SELECT DISTINCT c.id, c.nvr_id, c.name, c.channel_no, c.ip_address, c.http_port, c.is_public, c.status, c.last_checked_at
                 FROM cameras c
                 JOIN nvrs n ON n.id = c.nvr_id
                 LEFT JOIN permissions pc ON pc.scope_type = "camera" AND pc.scope_id = c.id AND pc.user_id = ?
                 LEFT JOIN permissions pn ON pn.scope_type = "nvr" AND pn.scope_id = c.nvr_id AND pn.user_id = ?
                 WHERE c.is_public = 1 OR n.is_public = 1 OR pc.id IS NOT NULL OR pn.id IS NOT NULL
                 ORDER BY c.name'
            );
            $stmt->execute([$userId, $userId]);
            $cameras = $stmt->fetchAll();

            // An NVR is shown either because it's public/granted itself, or
            // because at least one of its cameras is individually visible
            // (e.g. a camera-level grant on an otherwise private NVR).
            $visibleCameraNvrIds = array_unique(array_column($cameras, 'nvr_id'));

            $stmt = $pdo->prepare(
                'SELECT DISTINCT n.id, n.name, n.location, n.ip_address, n.http_port, n.is_public, n.status, n.last_checked_at
                 FROM nvrs n
                 LEFT JOIN permissions p ON p.scope_type = "nvr" AND p.scope_id = n.id AND p.user_id = ?
                 WHERE n.is_public = 1 OR p.id IS NOT NULL' .
                (!empty($visibleCameraNvrIds)
                    ? ' OR n.id IN (' . implode(',', array_map('intval', $visibleCameraNvrIds)) . ')'
                    : '') .
                ' ORDER BY n.name'
            );
            $stmt->execute([$userId]);
            $nvrs = $stmt->fetchAll();
        }

        $byNvr = [];
        foreach ($cameras as $cam) {
            $byNvr[$cam['nvr_id']][] = $cam;
        }
        foreach ($nvrs as &$nvr) {
            $nvr['cameras'] = $byNvr[$nvr['id']] ?? [];
        }
        return $nvrs;
    }

    /** True if the given user is allowed to open this camera. */
    public static function userCanAccessCamera(array $user, int $cameraId): bool
    {
        if (in_array($user['role'], ['admin', 'super_admin'], true)) {
            return true;
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT c.is_public,
                    (SELECT COUNT(*) FROM permissions WHERE scope_type = "camera" AND scope_id = c.id AND user_id = ?) AS direct_grant,
                    (SELECT COUNT(*) FROM permissions WHERE scope_type = "nvr" AND scope_id = c.nvr_id AND user_id = ?) AS nvr_grant
             FROM cameras c WHERE c.id = ?'
        );
        $stmt->execute([$user['id'], $user['id'], $cameraId]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        return $row['is_public'] == 1 || $row['direct_grant'] > 0 || $row['nvr_grant'] > 0;
    }

    /** True if the given user is allowed to open this NVR. */
    public static function userCanAccessNvr(array $user, int $nvrId): bool
    {
        if (in_array($user['role'], ['admin', 'super_admin'], true)) {
            return true;
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT is_public,
                    (SELECT COUNT(*) FROM permissions WHERE scope_type = "nvr" AND scope_id = nvrs.id AND user_id = ?) AS direct_grant
             FROM nvrs WHERE id = ?'
        );
        $stmt->execute([$user['id'], $nvrId]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        return $row['is_public'] == 1 || $row['direct_grant'] > 0;
    }
}

<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

/**
 * Service d'audit : trace toutes les actions importantes
 */
class AuditService
{
    /**
     * Enregistre une action dans le journal d'audit
     *
     * @param string     $action      Ex: CREATE_CAMPAIGN, VALIDATE_LOCATION, LOGIN
     * @param string|null $entityType Ex: campaign, location, inventory_item
     * @param int|null   $entityId    ID de l'entité concernée
     * @param array|null $oldValues   Valeurs avant modification
     * @param array|null $newValues   Valeurs après modification
     * @param int|null   $userId      Forcer un user_id (par défaut : utilisateur courant)
     * @param int|null   $orgId       Forcer un org_id
     */
    public static function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null,
        ?int $orgId = null
    ): void {
        try {
            $userId = $userId ?? Auth::id();
            $orgId  = $orgId ?? Auth::orgId();
            $ip     = self::getIp();
            $ua     = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

            Database::query(
                'INSERT INTO audit_logs
                    (organization_id, user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $orgId,
                    $userId,
                    $action,
                    $entityType,
                    $entityId,
                    $oldValues  ? json_encode($oldValues,  JSON_UNESCAPED_UNICODE) : null,
                    $newValues  ? json_encode($newValues,  JSON_UNESCAPED_UNICODE) : null,
                    $ip,
                    $ua,
                ]
            );
        } catch (\Throwable $e) {
            // L'audit ne doit jamais bloquer l'application
            error_log('[AuditService] Erreur lors de l\'enregistrement : ' . $e->getMessage());
        }
    }

    /**
     * Récupère le journal d'audit pour une entité
     */
    public static function getForEntity(string $entityType, int $entityId): array
    {
        return Database::fetchAll(
            'SELECT al.*, u.name as user_name, u.email as user_email
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.entity_type = ? AND al.entity_id = ?
             ORDER BY al.created_at DESC',
            [$entityType, $entityId]
        );
    }

    /**
     * Récupère le journal d'audit pour une organisation (paginé)
     */
    public static function getForOrg(int $orgId, int $limit = 100, int $offset = 0): array
    {
        return Database::fetchAll(
            'SELECT al.*, u.name as user_name
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.organization_id = ?
             ORDER BY al.created_at DESC
             LIMIT ? OFFSET ?',
            [$orgId, $limit, $offset]
        );
    }

    private static function getIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                return trim(explode(',', $_SERVER[$key])[0]);
            }
        }
        return '0.0.0.0';
    }
}

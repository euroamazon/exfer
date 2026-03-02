<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

/**
 * Service de gestion des anomalies
 *
 * Workflow : OPEN → INVESTIGATION → RESOLVED / REJECTED
 */
class AnomalyService
{
    /**
     * Crée une anomalie (déduplique si la même anomalie non résolue existe déjà)
     */
    public function create(
        string $type,
        string $severity,
        int $campaignId,
        ?int $locationId,
        ?int $itemId,
        string $description,
        array $context = []
    ): int {
        // Dédupliquer : si une anomalie OPEN/INVESTIGATION du même type sur le même item existe, ne pas créer
        if ($itemId) {
            $existing = Database::fetchOne(
                'SELECT id FROM anomalies
                 WHERE campaign_id = ? AND item_id = ? AND type = ? AND status IN ("OPEN","INVESTIGATION")',
                [$campaignId, $itemId, $type]
            );
            if ($existing) {
                return (int)$existing['id'];
            }
        }

        $orgId  = Auth::orgId() ?? Database::fetchScalar(
            'SELECT organization_id FROM campaigns WHERE id = ?', [$campaignId]
        );

        $id = Database::insert('anomalies', [
            'organization_id' => $orgId,
            'campaign_id'     => $campaignId,
            'location_id'     => $locationId,
            'item_id'         => $itemId,
            'type'            => $type,
            'status'          => 'OPEN',
            'severity'        => $severity,
            'description'     => $description,
            'context_json'    => $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
            'detected_at'     => date('Y-m-d H:i:s'),
            'detected_by'     => Auth::id(),
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        return (int)$id;
    }

    /**
     * Passe une anomalie en INVESTIGATION
     */
    public function investigate(int $id, string $notes): void
    {
        $this->transition($id, 'INVESTIGATION', $notes, 'investigated_by', 'investigated_at', 'investigation_notes');
    }

    /**
     * Résout une anomalie
     */
    public function resolve(int $id, string $notes): void
    {
        $this->transition($id, 'RESOLVED', $notes, 'resolved_by', 'resolved_at', 'resolution_notes');
    }

    /**
     * Rejette une anomalie
     */
    public function reject(int $id, string $notes): void
    {
        $this->transition($id, 'REJECTED', $notes, 'resolved_by', 'resolved_at', 'resolution_notes');
    }

    /**
     * Retourne les anomalies bloquantes d'un local
     */
    public function getBlockingAnomalies(int $locationId): array
    {
        return Database::fetchAll(
            'SELECT * FROM anomalies
             WHERE location_id = ? AND severity = "BLOCKING" AND status IN ("OPEN","INVESTIGATION")',
            [$locationId]
        );
    }

    /**
     * Retourne les anomalies d'une campagne (filtrées)
     */
    public function getByCampaign(int $campaignId, array $filters = []): array
    {
        $sql    = 'SELECT a.*, u1.name as detected_by_name, u2.name as resolved_by_name,
                          i.code_immo, l.code_local
                   FROM anomalies a
                   LEFT JOIN users u1 ON u1.id = a.detected_by
                   LEFT JOIN users u2 ON u2.id = a.resolved_by
                   LEFT JOIN inventory_items i ON i.id = a.item_id
                   LEFT JOIN locations l ON l.id = a.location_id
                   WHERE a.campaign_id = ?';
        $params = [$campaignId];

        if (!empty($filters['type'])) {
            $sql .= ' AND a.type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND a.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['severity'])) {
            $sql .= ' AND a.severity = ?';
            $params[] = $filters['severity'];
        }

        $sql .= ' ORDER BY a.created_at DESC';

        return Database::fetchAll($sql, $params);
    }

    /**
     * Stats des anomalies par type pour une campagne
     */
    public function getStats(int $campaignId): array
    {
        return Database::fetchAll(
            'SELECT type, severity, status, COUNT(*) as count
             FROM anomalies WHERE campaign_id = ?
             GROUP BY type, severity, status',
            [$campaignId]
        );
    }

    /**
     * Auto-résoudre les anomalies d'un item quand le problème est corrigé
     */
    public function autoResolve(int $itemId, string $type): void
    {
        Database::query(
            'UPDATE anomalies SET status = "RESOLVED", resolved_at = NOW(), resolved_by = ?,
             resolution_notes = "Résolu automatiquement suite à correction"
             WHERE item_id = ? AND type = ? AND status IN ("OPEN","INVESTIGATION")',
            [Auth::id(), $itemId, $type]
        );
    }

    // ─── Privé ────────────────────────────────────────────────────────────────

    private function transition(
        int $id,
        string $newStatus,
        string $notes,
        string $byField,
        string $atField,
        string $notesField
    ): void {
        $anomaly = Database::fetchOne('SELECT * FROM anomalies WHERE id = ?', [$id]);
        if (!$anomaly) {
            throw new \RuntimeException("Anomalie #$id introuvable.");
        }

        Database::query(
            "UPDATE anomalies SET status = ?, {$byField} = ?, {$atField} = NOW(), {$notesField} = ?, updated_at = NOW() WHERE id = ?",
            [$newStatus, Auth::id(), $notes, $id]
        );

        AuditService::log('ANOMALY_TRANSITION', 'anomaly', $id, ['status' => $anomaly['status']], ['status' => $newStatus]);
    }
}

<?php

namespace App\Services;

use App\Core\Database;

/**
 * Service de gestion des rouleaux d'étiquettes
 *
 * Règles :
 * - Un seul rouleau OPEN par agent + campagne
 * - Pas de chevauchement entre rouleaux d'une même campagne
 * - Consommation atomique via SELECT FOR UPDATE
 * - Actions : SKIP_LOST / SKIP_DAMAGED
 */
class LabelRollService
{
    /**
     * Crée un nouveau rouleau pour un agent
     * Vérifie l'absence de chevauchement
     */
    public static function create(
        int $orgId,
        int $campaignId,
        int $agentId,
        string $startCode,
        string $endCode,
        array $config
    ): array {
        // Vérifier qu'il n'y a pas déjà un rouleau OPEN pour cet agent
        $existing = self::getOpenRoll($agentId, $campaignId);
        if ($existing) {
            throw new \RuntimeException("L'agent a déjà un rouleau ouvert (#{$existing['id']}: {$existing['start_code']}-{$existing['end_code']}).");
        }

        // Valider le format des codes de début et fin
        $formatStart = CodeValidationService::validateFormat($startCode, $config);
        if (!$formatStart['valid']) {
            throw new \InvalidArgumentException("Code de début invalide: {$formatStart['error']}");
        }

        $formatEnd = CodeValidationService::validateFormat($endCode, $config);
        if (!$formatEnd['valid']) {
            throw new \InvalidArgumentException("Code de fin invalide: {$formatEnd['error']}");
        }

        // Vérifier que start <= end
        if (CodeValidationService::compareCode($startCode, $endCode) > 0) {
            throw new \InvalidArgumentException("Le code de début doit être inférieur ou égal au code de fin.");
        }

        // Vérifier les chevauchements avec les autres rouleaux de la campagne
        self::validateNoOverlap($campaignId, $startCode, $endCode);

        // Créer le rouleau
        $id = Database::insert('label_rolls', [
            'organization_id' => $orgId,
            'campaign_id'     => $campaignId,
            'agent_id'        => $agentId,
            'start_code'      => $startCode,
            'end_code'        => $endCode,
            'next_code'       => $startCode,
            'status'          => 'OPEN',
            'skipped_codes'   => null,
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        AuditService::log('CREATE_LABEL_ROLL', 'label_roll', (int)$id, null, [
            'start_code' => $startCode, 'end_code' => $endCode
        ]);

        return Database::fetchOne('SELECT * FROM label_rolls WHERE id = ?', [$id]);
    }

    /**
     * Retourne le rouleau OPEN de l'agent (ou null)
     */
    public static function getOpenRoll(int $agentId, int $campaignId): ?array
    {
        return Database::fetchOne(
            'SELECT * FROM label_rolls WHERE agent_id = ? AND campaign_id = ? AND status = "OPEN" LIMIT 1',
            [$agentId, $campaignId]
        );
    }

    /**
     * Consomme le prochain code du rouleau (atomique avec transaction)
     * Retourne le code attribué
     *
     * @throws \RuntimeException si le rouleau est épuisé ou fermé
     */
    public static function consumeNextCode(int $rollId): string
    {
        Database::beginTransaction();

        try {
            // SELECT FOR UPDATE pour éviter les race conditions
            $roll = Database::fetchOne(
                'SELECT * FROM label_rolls WHERE id = ? FOR UPDATE',
                [$rollId]
            );

            if (!$roll) {
                throw new \RuntimeException("Rouleau introuvable.");
            }

            if ($roll['status'] !== 'OPEN') {
                throw new \RuntimeException("Le rouleau est {$roll['status']} et ne peut plus être utilisé.");
            }

            $code     = $roll['next_code'];
            $config   = ['code_length' => strlen($code), 'code_type' => ctype_digit($code) ? 'NUMERIC' : 'ALPHANUM'];
            $nextCode = CodeValidationService::incrementCode($code, strlen($code), $config['code_type']);

            // Vérifier si c'est le dernier code
            if (CodeValidationService::compareCode($nextCode, $roll['end_code']) > 0) {
                // Le rouleau sera épuisé après ce code
                Database::query(
                    'UPDATE label_rolls SET next_code = ?, status = "EXHAUSTED", updated_at = NOW() WHERE id = ?',
                    [$nextCode, $rollId]
                );
            } else {
                Database::query(
                    'UPDATE label_rolls SET next_code = ?, updated_at = NOW() WHERE id = ?',
                    [$nextCode, $rollId]
                );
            }

            Database::commit();
            return $code;

        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Saute un code (LOST ou DAMAGED)
     */
    public static function skipCode(int $rollId, string $reason, AnomalyService $anomalyService): string
    {
        Database::beginTransaction();

        try {
            $roll = Database::fetchOne(
                'SELECT * FROM label_rolls WHERE id = ? FOR UPDATE',
                [$rollId]
            );

            if (!$roll || $roll['status'] !== 'OPEN') {
                throw new \RuntimeException("Rouleau indisponible.");
            }

            $skippedCode = $roll['next_code'];
            $config      = ['code_length' => strlen($skippedCode), 'code_type' => ctype_digit($skippedCode) ? 'NUMERIC' : 'ALPHANUM'];
            $nextCode    = CodeValidationService::incrementCode($skippedCode, strlen($skippedCode), $config['code_type']);

            // Enregistrer le code sauté
            $skipped   = json_decode($roll['skipped_codes'] ?? '[]', true) ?: [];
            $skipped[] = ['code' => $skippedCode, 'reason' => $reason, 'skipped_at' => date('Y-m-d H:i:s')];

            // Mettre à jour le rouleau
            $newStatus = (CodeValidationService::compareCode($nextCode, $roll['end_code']) > 0)
                ? 'EXHAUSTED'
                : 'OPEN';

            Database::query(
                'UPDATE label_rolls SET next_code = ?, status = ?, skipped_codes = ?, updated_at = NOW() WHERE id = ?',
                [$nextCode, $newStatus, json_encode($skipped), $rollId]
            );

            Database::commit();

            // Créer une anomalie SAUT_ROULEAU
            $anomalyService->create(
                'SAUT_ROULEAU',
                'WARNING',
                $roll['campaign_id'],
                null,
                null,
                "Code {$skippedCode} sauté ({$reason})",
                ['code' => $skippedCode, 'reason' => $reason, 'roll_id' => $rollId]
            );

            AuditService::log('SKIP_CODE', 'label_roll', $rollId, null, ['code' => $skippedCode, 'reason' => $reason]);

            return $skippedCode;

        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Ferme un rouleau manuellement
     */
    public static function closeRoll(int $rollId): array
    {
        $roll = Database::fetchOne('SELECT * FROM label_rolls WHERE id = ?', [$rollId]);
        if (!$roll) {
            throw new \RuntimeException("Rouleau introuvable.");
        }

        if (!in_array($roll['status'], ['OPEN', 'EXHAUSTED'])) {
            throw new \RuntimeException("Ce rouleau est déjà fermé.");
        }

        Database::query(
            'UPDATE label_rolls SET status = "CLOSED", closed_at = NOW(), updated_at = NOW() WHERE id = ?',
            [$rollId]
        );

        AuditService::log('CLOSE_LABEL_ROLL', 'label_roll', $rollId);

        // Calculer le récapitulatif
        $skipped    = json_decode($roll['skipped_codes'] ?? '[]', true) ?: [];
        $totalCodes = self::countRange($roll['start_code'], $roll['end_code']);
        $used       = $totalCodes - count($skipped) - (CodeValidationService::compareCode($roll['next_code'], $roll['end_code']) <= 0
            ? (int)self::countRange($roll['next_code'], $roll['end_code'])
            : 0);

        return [
            'roll'        => $roll,
            'total_codes' => $totalCodes,
            'used'        => max(0, $used),
            'skipped'     => count($skipped),
            'skipped_list'=> $skipped,
        ];
    }

    /**
     * Vérifie l'absence de chevauchement avec les rouleaux existants de la campagne
     */
    public static function validateNoOverlap(int $campaignId, string $startCode, string $endCode, ?int $excludeRollId = null): void
    {
        $sql    = 'SELECT * FROM label_rolls WHERE campaign_id = ? AND status != "CLOSED"';
        $params = [$campaignId];

        if ($excludeRollId) {
            $sql    .= ' AND id != ?';
            $params[] = $excludeRollId;
        }

        $existingRolls = Database::fetchAll($sql, $params);

        foreach ($existingRolls as $roll) {
            // Chevauchement si les plages se croisent
            $noOverlap = CodeValidationService::compareCode($endCode, $roll['start_code']) < 0
                || CodeValidationService::compareCode($startCode, $roll['end_code']) > 0;

            if (!$noOverlap) {
                throw new \RuntimeException(
                    "La plage {$startCode}-{$endCode} chevauche le rouleau #{$roll['id']} ({$roll['start_code']}-{$roll['end_code']})."
                );
            }
        }
    }

    /**
     * Compte le nombre de codes dans une plage (numérique seulement)
     */
    private static function countRange(string $start, string $end): int
    {
        if (ctype_digit($start) && ctype_digit($end)) {
            return max(0, (int)$end - (int)$start + 1);
        }
        return 0;
    }
}

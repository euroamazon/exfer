<?php

namespace App\Services;

use App\Core\Database;

/**
 * Service de contrôle qualité
 *
 * Effectue des contrôles automatiques au niveau du local et de la campagne
 */
class QualityControlService
{
    private AnomalyService $anomalyService;

    public function __construct()
    {
        $this->anomalyService = new AnomalyService();
    }

    /**
     * Lance tous les contrôles qualité sur un local
     * Crée les anomalies en BDD et retourne le résumé
     */
    public function runLocationChecks(int $locationId): array
    {
        $location = Database::fetchOne(
            'SELECT l.*, c.config as campaign_config, c.id as campaign_id, c.organization_id
             FROM locations l JOIN campaigns c ON c.id = l.campaign_id
             WHERE l.id = ?',
            [$locationId]
        );

        if (!$location) {
            throw new \RuntimeException("Local #{$locationId} introuvable.");
        }

        $config     = json_decode($location['campaign_config'] ?? '{}', true) ?: [];
        $campaignId = (int)$location['campaign_id'];
        $orgId      = (int)$location['organization_id'];
        $results    = [];

        // Charger tous les items du local
        $items = Database::fetchAll(
            'SELECT * FROM inventory_items WHERE location_id = ? ORDER BY code_immo',
            [$locationId]
        );

        // 1. Doublons de code dans le local
        $results[] = $this->checkDuplicateCodes($items, $campaignId, $locationId, $config);

        // 2. Format invalide des codes
        $results[] = $this->checkCodeFormats($items, $campaignId, $locationId, $config);

        // 3. Codes hors plage
        $results[] = $this->checkCodeRange($items, $campaignId, $locationId, $config);

        // 4. Numéros de série dupliqués
        $results[] = $this->checkDuplicateSerials($items, $campaignId, $locationId);

        // 5. Codes hors rouleau (si rouleau activé)
        if (!empty($config['label_roll_enabled'])) {
            $results[] = $this->checkCodesOutOfRoll($items, $campaignId, $locationId);
        }

        // 6. Photo manquante (si requise)
        if (!empty($config['require_photo'])) {
            $results[] = $this->checkMissingPhotos($items, $campaignId, $locationId);
        }

        // 7. Numéro de série manquant (si requis)
        if (!empty($config['require_serial'])) {
            $results[] = $this->checkMissingSerials($items, $campaignId, $locationId);
        }

        // Compter les anomalies bloquantes
        $blockingAnomalies = $this->anomalyService->getBlockingAnomalies($locationId);

        return [
            'location_id'       => $locationId,
            'items_checked'     => count($items),
            'checks'            => array_merge(...$results),
            'blocking_count'    => count($blockingAnomalies),
            'has_blocking'      => !empty($blockingAnomalies),
            'blocking_anomalies'=> $blockingAnomalies,
        ];
    }

    /**
     * Lance les contrôles qualité au niveau de la campagne entière
     */
    public function runCampaignChecks(int $campaignId): array
    {
        $campaign = Database::fetchOne('SELECT * FROM campaigns WHERE id = ?', [$campaignId]);
        if (!$campaign) {
            throw new \RuntimeException("Campagne #{$campaignId} introuvable.");
        }

        $config  = json_decode($campaign['config'] ?? '{}', true) ?: [];
        $results = [];

        // 1. Codes manquants dans la plage min..max
        if (!empty($config['min_code']) && !empty($config['max_code'])) {
            $results[] = $this->checkMissingCodes($campaignId, $config);
        }

        // 2. Codes de rouleaux consommés non assignés à des items
        $results[] = $this->checkUnusedRollCodes($campaignId, $config);

        return [
            'campaign_id' => $campaignId,
            'checks'      => array_merge(...$results),
        ];
    }

    // ─── Contrôles individuels ────────────────────────────────────────────────

    private function checkDuplicateCodes(array $items, int $campaignId, int $locationId, array $config): array
    {
        $checks  = [];
        $codes   = array_column($items, 'code_immo', 'id');
        $seen    = [];

        foreach ($codes as $itemId => $code) {
            if (isset($seen[$code])) {
                // Doublon détecté
                $aId = $this->anomalyService->create(
                    'DOUBLON_CODE',
                    'BLOCKING',
                    $campaignId,
                    $locationId,
                    $itemId,
                    "Doublon du code {$code} (déjà utilisé par l'article #{$seen[$code]})",
                    ['duplicate_of' => $seen[$code], 'code' => $code]
                );
                $checks[] = ['type' => 'DOUBLON_CODE', 'severity' => 'BLOCKING', 'item_id' => $itemId, 'anomaly_id' => $aId];
            } else {
                $seen[$code] = $itemId;
            }
        }

        // Vérifier aussi les doublons cross-campagne si scope ORGANIZATION
        if (($config['unique_scope'] ?? 'ORGANIZATION') === 'ORGANIZATION') {
            foreach ($items as $item) {
                $duplicate = Database::fetchOne(
                    'SELECT id, campaign_id FROM inventory_items
                     WHERE code_immo = ? AND organization_id = ? AND campaign_id != ? AND id != ?',
                    [$item['code_immo'], $item['organization_id'], $campaignId, $item['id']]
                );
                if ($duplicate) {
                    $aId = $this->anomalyService->create(
                        'DOUBLON_CODE',
                        'BLOCKING',
                        $campaignId,
                        $locationId,
                        (int)$item['id'],
                        "Code {$item['code_immo']} déjà utilisé dans la campagne #{$duplicate['campaign_id']}",
                        ['duplicate_in_campaign' => $duplicate['campaign_id']]
                    );
                    $checks[] = ['type' => 'DOUBLON_CODE', 'severity' => 'BLOCKING', 'item_id' => $item['id'], 'anomaly_id' => $aId];
                }
            }
        }

        return $checks;
    }

    private function checkCodeFormats(array $items, int $campaignId, int $locationId, array $config): array
    {
        $checks = [];
        foreach ($items as $item) {
            $result = CodeValidationService::validateFormat($item['code_immo'], $config);
            if (!$result['valid']) {
                $aId = $this->anomalyService->create(
                    'FORMAT_INVALIDE',
                    'BLOCKING',
                    $campaignId,
                    $locationId,
                    (int)$item['id'],
                    "Format invalide du code {$item['code_immo']}: {$result['error']}",
                    ['code' => $item['code_immo'], 'error' => $result['error']]
                );
                $checks[] = ['type' => 'FORMAT_INVALIDE', 'severity' => 'BLOCKING', 'item_id' => $item['id'], 'anomaly_id' => $aId];
            }
        }
        return $checks;
    }

    private function checkCodeRange(array $items, int $campaignId, int $locationId, array $config): array
    {
        $checks = [];
        if (empty($config['min_code']) && empty($config['max_code'])) {
            return $checks;
        }

        foreach ($items as $item) {
            $result = CodeValidationService::validateRange($item['code_immo'], $config);
            if (!$result['valid']) {
                $aId = $this->anomalyService->create(
                    'INCOHERENCE',
                    'ERROR',
                    $campaignId,
                    $locationId,
                    (int)$item['id'],
                    "Code hors plage: {$result['error']}",
                    ['code' => $item['code_immo']]
                );
                $checks[] = ['type' => 'INCOHERENCE', 'severity' => 'ERROR', 'item_id' => $item['id'], 'anomaly_id' => $aId];
            }
        }
        return $checks;
    }

    private function checkDuplicateSerials(array $items, int $campaignId, int $locationId): array
    {
        $checks  = [];
        $serials = [];

        foreach ($items as $item) {
            if (empty($item['serial_number'])) {
                continue;
            }
            $sn = $item['serial_number'];
            if (isset($serials[$sn])) {
                $aId = $this->anomalyService->create(
                    'INCOHERENCE',
                    'WARNING',
                    $campaignId,
                    $locationId,
                    (int)$item['id'],
                    "Numéro de série dupliqué: {$sn} (déjà sur l'article #{$serials[$sn]})",
                    ['serial_number' => $sn, 'duplicate_of' => $serials[$sn]]
                );
                $checks[] = ['type' => 'INCOHERENCE', 'severity' => 'WARNING', 'item_id' => $item['id'], 'anomaly_id' => $aId];
            } else {
                $serials[$sn] = $item['id'];
            }
        }
        return $checks;
    }

    private function checkCodesOutOfRoll(array $items, int $campaignId, int $locationId): array
    {
        $checks = [];
        // Charger tous les rouleaux de la campagne
        $rolls = Database::fetchAll(
            'SELECT * FROM label_rolls WHERE campaign_id = ?', [$campaignId]
        );

        foreach ($items as $item) {
            // Un item sans label_roll_id mais avec un code dans la plage d'un rouleau
            if ($item['label_roll_id']) {
                continue; // Assigné à un rouleau
            }
            if ($item['imported_from']) {
                continue; // Importé, pas de rouleau requis
            }

            // Vérifier si le code est dans la plage d'un rouleau
            $inRoll = false;
            foreach ($rolls as $roll) {
                if (
                    CodeValidationService::compareCode($item['code_immo'], $roll['start_code']) >= 0
                    && CodeValidationService::compareCode($item['code_immo'], $roll['end_code']) <= 0
                ) {
                    $inRoll = true;
                    break;
                }
            }

            if (!$inRoll && !empty($rolls)) {
                $aId = $this->anomalyService->create(
                    'CODE_HORS_ROULEAU',
                    'WARNING',
                    $campaignId,
                    $locationId,
                    (int)$item['id'],
                    "Le code {$item['code_immo']} n'appartient à aucun rouleau",
                    ['code' => $item['code_immo']]
                );
                $checks[] = ['type' => 'CODE_HORS_ROULEAU', 'severity' => 'WARNING', 'item_id' => $item['id'], 'anomaly_id' => $aId];
            }
        }
        return $checks;
    }

    private function checkMissingPhotos(array $items, int $campaignId, int $locationId): array
    {
        $checks = [];
        foreach ($items as $item) {
            $photos = json_decode($item['photos_json'] ?? '[]', true) ?: [];
            if (empty($photos)) {
                $aId = $this->anomalyService->create(
                    'INCOHERENCE',
                    'ERROR',
                    $campaignId,
                    $locationId,
                    (int)$item['id'],
                    "Photo obligatoire manquante pour le code {$item['code_immo']}",
                    ['code' => $item['code_immo']]
                );
                $checks[] = ['type' => 'INCOHERENCE', 'severity' => 'ERROR', 'item_id' => $item['id'], 'anomaly_id' => $aId];
            }
        }
        return $checks;
    }

    private function checkMissingSerials(array $items, int $campaignId, int $locationId): array
    {
        $checks = [];
        foreach ($items as $item) {
            if (empty($item['serial_number'])) {
                $aId = $this->anomalyService->create(
                    'INCOHERENCE',
                    'WARNING',
                    $campaignId,
                    $locationId,
                    (int)$item['id'],
                    "Numéro de série manquant pour le code {$item['code_immo']}",
                    []
                );
                $checks[] = ['type' => 'INCOHERENCE', 'severity' => 'WARNING', 'item_id' => $item['id'], 'anomaly_id' => $aId];
            }
        }
        return $checks;
    }

    private function checkMissingCodes(int $campaignId, array $config): array
    {
        $checks  = [];
        $minCode = $config['min_code'];
        $maxCode = $config['max_code'];
        $length  = (int)($config['code_length'] ?? 6);

        // Récupérer tous les codes existants
        $existingCodes = Database::fetchAll(
            'SELECT code_immo FROM inventory_items WHERE campaign_id = ? ORDER BY code_immo',
            [$campaignId]
        );
        $existingSet = array_flip(array_column($existingCodes, 'code_immo'));

        // Vérifier aussi les codes sautés dans les rouleaux
        $rolls = Database::fetchAll('SELECT * FROM label_rolls WHERE campaign_id = ?', [$campaignId]);
        $skippedCodes = [];
        foreach ($rolls as $roll) {
            $skipped = json_decode($roll['skipped_codes'] ?? '[]', true) ?: [];
            foreach ($skipped as $s) {
                $skippedCodes[$s['code']] = true;
            }
        }

        // Itérer de min à max (limité à 10000 pour performance)
        $current = $minCode;
        $limit   = 10000;
        $i       = 0;

        while (CodeValidationService::compareCode($current, $maxCode) <= 0 && $i < $limit) {
            if (!isset($existingSet[$current]) && !isset($skippedCodes[$current])) {
                $aId = $this->anomalyService->create(
                    'MISSING_CODE',
                    'WARNING',
                    $campaignId,
                    null,
                    null,
                    "Code manquant dans la plage : {$current}",
                    ['code' => $current]
                );
                $checks[] = ['type' => 'MISSING_CODE', 'severity' => 'WARNING', 'code' => $current, 'anomaly_id' => $aId];
            }

            $current = CodeValidationService::incrementCode($current, $length, $config['code_type'] ?? 'NUMERIC');
            $i++;
        }

        return $checks;
    }

    private function checkUnusedRollCodes(int $campaignId, array $config): array
    {
        // Codes des rouleaux qui ont été consommés (next_code avancé) mais sans article correspondant
        // Contrôle léger : vérifier que chaque code entre start et (next_code - 1) a un article
        return []; // Implémentation complète dans un module de rapport
    }
}

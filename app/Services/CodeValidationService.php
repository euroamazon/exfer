<?php

namespace App\Services;

use App\Core\Database;

/**
 * Service de validation et de gestion des codes d'immobilisation
 */
class CodeValidationService
{
    /**
     * Valide le format d'un code selon la configuration de la campagne
     *
     * @param string $code   Code à valider
     * @param array  $config Configuration campagne (config JSON décodé)
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateFormat(string $code, array $config): array
    {
        $length    = (int)($config['code_length'] ?? 6);
        $codeType  = $config['code_type'] ?? 'NUMERIC';

        // Vérifier la longueur
        if (strlen($code) !== $length) {
            return ['valid' => false, 'error' => "Le code doit faire exactement {$length} caractères (actuellement " . strlen($code) . ")."];
        }

        // Vérifier le type
        if ($codeType === 'NUMERIC') {
            if (!ctype_digit($code)) {
                return ['valid' => false, 'error' => "Le code doit être entièrement numérique."];
            }
        } elseif ($codeType === 'ALPHANUM') {
            if (!ctype_alnum($code)) {
                return ['valid' => false, 'error' => "Le code doit être alphanumérique (lettres et chiffres uniquement)."];
            }
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Valide que le code est dans la plage min/max configurée
     */
    public static function validateRange(string $code, array $config): array
    {
        $minCode = $config['min_code'] ?? null;
        $maxCode = $config['max_code'] ?? null;

        if ($minCode !== null && self::compareCode($code, (string)$minCode) < 0) {
            return ['valid' => false, 'error' => "Le code {$code} est inférieur au minimum autorisé ({$minCode})."];
        }

        if ($maxCode !== null && self::compareCode($code, (string)$maxCode) > 0) {
            return ['valid' => false, 'error' => "Le code {$code} dépasse le maximum autorisé ({$maxCode})."];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Valide l'unicité d'un code selon le scope configuré
     *
     * @param string $uniqueScope 'CAMPAIGN' ou 'ORGANIZATION'
     * @param int|null $excludeItemId ID de l'article à exclure (pour les modifications)
     */
    public static function validateUniqueness(
        string $code,
        int $campaignId,
        int $orgId,
        string $uniqueScope = 'ORGANIZATION',
        ?int $excludeItemId = null
    ): array {
        if ($uniqueScope === 'CAMPAIGN') {
            $sql    = 'SELECT id FROM inventory_items WHERE campaign_id = ? AND code_immo = ?';
            $params = [$campaignId, $code];
        } else {
            // ORGANIZATION : unicité sur toute l'organisation
            $sql    = 'SELECT id FROM inventory_items WHERE organization_id = ? AND code_immo = ?';
            $params = [$orgId, $code];
        }

        if ($excludeItemId) {
            $sql    .= ' AND id != ?';
            $params[] = $excludeItemId;
        }

        $existing = Database::fetchOne($sql, $params);

        if ($existing) {
            return ['valid' => false, 'error' => "Le code {$code} est déjà utilisé (doublon)."];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Suggère le prochain code attendu dans la séquence
     */
    public static function suggestNextCode(int $campaignId, int $orgId, array $config): string
    {
        $length    = (int)($config['code_length'] ?? 6);
        $minCode   = $config['min_code'] ?? null;
        $codeType  = $config['code_type'] ?? 'NUMERIC';

        // 1. Chercher si un rouleau OPEN existe pour la campagne
        $roll = Database::fetchOne(
            'SELECT next_code FROM label_rolls
             WHERE campaign_id = ? AND status = "OPEN"
             ORDER BY id DESC LIMIT 1',
            [$campaignId]
        );

        if ($roll) {
            return $roll['next_code'];
        }

        // 2. Trouver le max des codes existants
        $maxItem = Database::fetchScalar(
            'SELECT MAX(code_immo) FROM inventory_items WHERE campaign_id = ?',
            [$campaignId]
        );

        if ($maxItem) {
            return self::incrementCode((string)$maxItem, $length, $codeType);
        }

        // 3. Retourner le code min ou le code de départ par défaut
        if ($minCode) {
            return str_pad((string)$minCode, $length, '0', STR_PAD_LEFT);
        }

        return str_pad('1', $length, '0', STR_PAD_LEFT);
    }

    /**
     * Incrémente un code de 1
     */
    public static function incrementCode(string $code, int $length, string $type = 'NUMERIC'): string
    {
        if ($type === 'NUMERIC') {
            $next = (string)((int)$code + 1);
            return str_pad($next, $length, '0', STR_PAD_LEFT);
        }

        // ALPHANUM : incrémentation base-36
        $chars  = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code   = strtoupper($code);
        $result = $code;
        $carry  = true;
        $len    = strlen($code);

        for ($i = $len - 1; $i >= 0 && $carry; $i--) {
            $pos = strpos($chars, $result[$i]);
            if ($pos === false) {
                $pos = 0;
            }
            $pos++;
            if ($pos >= strlen($chars)) {
                $result[$i] = '0';
                $carry = true;
            } else {
                $result[$i] = $chars[$pos];
                $carry = false;
            }
        }

        return str_pad($result, $length, '0', STR_PAD_LEFT);
    }

    /**
     * Compare deux codes (retourne -1, 0, ou 1)
     */
    public static function compareCode(string $a, string $b): int
    {
        // Pour les codes numériques : comparaison entière
        if (ctype_digit($a) && ctype_digit($b)) {
            return (int)$a <=> (int)$b;
        }
        return strcmp($a, $b);
    }

    /**
     * Vérifie si un code appartient à un rouleau de l'agent dans la campagne
     */
    public static function isInRoll(string $code, int $campaignId, int $agentId): bool
    {
        $rolls = Database::fetchAll(
            'SELECT * FROM label_rolls WHERE campaign_id = ? AND agent_id = ?',
            [$campaignId, $agentId]
        );

        foreach ($rolls as $roll) {
            if (
                self::compareCode($code, $roll['start_code']) >= 0
                && self::compareCode($code, $roll['end_code']) <= 0
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Valide complètement un code (format + plage + unicité)
     * Retourne une liste d'erreurs (vide = valide)
     */
    public static function validateFull(
        string $code,
        array $config,
        int $campaignId,
        int $orgId,
        ?int $excludeItemId = null
    ): array {
        $errors = [];

        $formatCheck = self::validateFormat($code, $config);
        if (!$formatCheck['valid']) {
            $errors[] = $formatCheck['error'];
        }

        $rangeCheck = self::validateRange($code, $config);
        if (!$rangeCheck['valid']) {
            $errors[] = $rangeCheck['error'];
        }

        $uniqueScope   = $config['unique_scope'] ?? 'ORGANIZATION';
        $uniqueCheck   = self::validateUniqueness($code, $campaignId, $orgId, $uniqueScope, $excludeItemId);
        if (!$uniqueCheck['valid']) {
            $errors[] = $uniqueCheck['error'];
        }

        return $errors;
    }
}

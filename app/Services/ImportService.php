<?php

namespace App\Services;

use App\Core\Database;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Service d'import Excel / CSV
 *
 * Modes : INSERT / UPDATE / UPSERT
 * Supporte le dry-run (simulation sans écriture)
 */
class ImportService
{
    private AnomalyService $anomalyService;
    private CodeValidationService $codeValidation;

    public function __construct()
    {
        $this->anomalyService = new AnomalyService();
        $this->codeValidation = new CodeValidationService();
    }

    /**
     * Parse un fichier XLSX ou CSV et retourne les données brutes
     *
     * @return array ['headers' => [...], 'rows' => [[col => val, ...]]]
     */
    public function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("Fichier introuvable: {$filePath}");
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($ext === 'csv') {
            return $this->parseCsv($filePath);
        }

        return $this->parseXlsx($filePath);
    }

    /**
     * Valide les lignes importées avant exécution
     *
     * @param array  $rows       Données parsées
     * @param array  $mapping    ['fichier_colonne' => 'db_champ']
     * @param int    $campaignId
     * @param string $mode       INSERT / UPDATE / UPSERT
     * @param bool   $isDryRun
     * @return array ['valid' => bool, 'errors' => [...], 'stats' => [...], 'preview' => [...]]
     */
    public function validate(array $rows, array $mapping, int $campaignId, int $orgId, string $mode, bool $isDryRun): array
    {
        $campaign = Database::fetchOne('SELECT * FROM campaigns WHERE id = ?', [$campaignId]);
        $config   = json_decode($campaign['config'] ?? '{}', true) ?: [];

        $errors  = [];
        $preview = [];
        $stats   = ['total' => count($rows), 'insert' => 0, 'update' => 0, 'skip' => 0, 'error' => 0];

        // Codes vus dans le fichier pour détecter les doublons internes
        $codesInFile = [];

        foreach ($rows as $rowIndex => $row) {
            $rowNum  = $rowIndex + 2; // Excel commence à la ligne 2 (ligne 1 = headers)
            $mapped  = $this->applyMapping($row, $mapping);
            $rowErrors = [];

            $code = $mapped['code_immo'] ?? null;

            if (empty($code)) {
                $rowErrors[] = "Code immobilisation manquant";
            } else {
                // Format
                $fmtCheck = CodeValidationService::validateFormat((string)$code, $config);
                if (!$fmtCheck['valid']) {
                    $rowErrors[] = "Format invalide: {$fmtCheck['error']}";
                }

                // Doublon dans le fichier
                if (isset($codesInFile[$code])) {
                    $rowErrors[] = "Doublon dans le fichier (déjà vu ligne {$codesInFile[$code]})";
                } else {
                    $codesInFile[$code] = $rowNum;
                }

                // Vérifier existence en BDD selon le mode
                $existingItem = Database::fetchOne(
                    'SELECT id FROM inventory_items WHERE campaign_id = ? AND code_immo = ?',
                    [$campaignId, $code]
                );

                if ($mode === 'INSERT' && $existingItem) {
                    $rowErrors[] = "Code {$code} déjà existant (mode INSERT)";
                } elseif ($mode === 'UPDATE' && !$existingItem) {
                    $rowErrors[] = "Code {$code} introuvable pour mise à jour (mode UPDATE)";
                }

                if (!empty($rowErrors)) {
                    $stats['error']++;
                } elseif ($existingItem) {
                    $stats['update']++;
                } else {
                    $stats['insert']++;
                }
            }

            if (!empty($rowErrors)) {
                $errors[] = ['row' => $rowNum, 'code' => $code, 'errors' => $rowErrors];
            }

            $preview[] = [
                'row'    => $rowNum,
                'code'   => $code,
                'data'   => $mapped,
                'errors' => $rowErrors,
                'action' => $this->determineAction($code, $campaignId, $mode, $rowErrors),
            ];
        }

        return [
            'valid'   => empty($errors),
            'errors'  => $errors,
            'stats'   => $stats,
            'preview' => array_slice($preview, 0, 100), // Limiter la preview à 100 lignes
        ];
    }

    /**
     * Exécute l'import (après validation)
     */
    public function execute(int $jobId): array
    {
        $job = Database::fetchOne('SELECT * FROM import_jobs WHERE id = ?', [$jobId]);
        if (!$job) {
            throw new \RuntimeException("Job d'import #{$jobId} introuvable.");
        }

        $campaignId = (int)$job['campaign_id'];
        $orgId      = (int)$job['organization_id'];
        $mode       = $job['mode'];
        $isDryRun   = (bool)$job['is_dry_run'];
        $mapping    = json_decode($job['column_mapping'] ?? '{}', true) ?: [];

        // Mettre à jour le statut
        Database::query('UPDATE import_jobs SET status = "PROCESSING", started_at = NOW() WHERE id = ?', [$jobId]);

        try {
            $rows   = $this->parseFile($job['filepath']);
            $data   = $rows['rows'];
            $stats  = ['total' => count($data), 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'anomalies' => 0];
            $errors = [];

            $campaign = Database::fetchOne('SELECT * FROM campaigns WHERE id = ?', [$campaignId]);
            $config   = json_decode($campaign['config'] ?? '{}', true) ?: [];

            // Trouver les locations existantes
            $locationCache = [];

            // Traitement par batch de 100
            $batches = array_chunk($data, 100);

            foreach ($batches as $batch) {
                if (!$isDryRun) {
                    Database::beginTransaction();
                }

                try {
                    foreach ($batch as $rowIndex => $row) {
                        $rowNum = $rowIndex + 2;
                        $mapped = $this->applyMapping($row, $mapping);
                        $code   = $mapped['code_immo'] ?? null;

                        if (empty($code)) {
                            $stats['skipped']++;
                            continue;
                        }

                        $existing = Database::fetchOne(
                            'SELECT * FROM inventory_items WHERE campaign_id = ? AND code_immo = ?',
                            [$campaignId, $code]
                        );

                        try {
                            if ($mode === 'INSERT') {
                                if ($existing) {
                                    $stats['skipped']++;
                                    continue;
                                }
                                $locationId = $this->resolveLocation($mapped, $campaignId, $orgId, $locationCache, $isDryRun);
                                if (!$isDryRun) {
                                    $this->insertItem($mapped, $campaignId, $orgId, $locationId, $jobId, $config);
                                }
                                $stats['inserted']++;
                            } elseif ($mode === 'UPDATE') {
                                if (!$existing) {
                                    $stats['skipped']++;
                                    continue;
                                }
                                if (!$isDryRun) {
                                    $this->updateItem($existing['id'], $mapped, $config);
                                }
                                $stats['updated']++;
                            } elseif ($mode === 'UPSERT') {
                                $locationId = $this->resolveLocation($mapped, $campaignId, $orgId, $locationCache, $isDryRun);
                                if ($existing) {
                                    if (!$isDryRun) {
                                        $this->updateItem($existing['id'], $mapped, $config);
                                    }
                                    $stats['updated']++;
                                } else {
                                    if (!$isDryRun) {
                                        $this->insertItem($mapped, $campaignId, $orgId, $locationId, $jobId, $config);
                                    }
                                    $stats['inserted']++;
                                }
                            }
                        } catch (\Throwable $e) {
                            $stats['errors']++;
                            $errors[] = ['row' => $rowNum, 'code' => $code, 'message' => $e->getMessage()];
                        }
                    }

                    if (!$isDryRun) {
                        Database::commit();
                    }
                } catch (\Throwable $e) {
                    if (!$isDryRun) {
                        Database::rollback();
                    }
                    throw $e;
                }
            }

            // Mettre à jour le job
            $finalStatus = $isDryRun ? 'DONE' : ($stats['errors'] > 0 ? 'DONE' : 'DONE');
            Database::query(
                'UPDATE import_jobs SET status = ?, stats_json = ?, error_log = ?, finished_at = NOW() WHERE id = ?',
                [$finalStatus, json_encode($stats), json_encode($errors), $jobId]
            );

            AuditService::log('IMPORT_EXECUTE', 'import_job', $jobId, null, $stats);

            return ['stats' => $stats, 'errors' => $errors];

        } catch (\Throwable $e) {
            Database::query('UPDATE import_jobs SET status = "FAILED", finished_at = NOW() WHERE id = ?', [$jobId]);
            throw $e;
        }
    }

    // ─── Privé ────────────────────────────────────────────────────────────────

    private function parseXlsx(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray(null, true, true, false);

        if (empty($rows)) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = array_map('trim', (array)array_shift($rows));
        $data    = [];

        foreach ($rows as $row) {
            if (empty(array_filter((array)$row))) {
                continue; // Ignorer les lignes vides
            }
            $mapped = [];
            foreach ($headers as $idx => $header) {
                if ($header) {
                    $mapped[$header] = $row[$idx] ?? null;
                }
            }
            $data[] = $mapped;
        }

        return ['headers' => $headers, 'rows' => $data];
    }

    private function parseCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException("Impossible d'ouvrir le fichier CSV.");
        }

        // Détecter l'encodage et convertir si nécessaire
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            fseek($handle, 0);
        }

        $headers = array_map('trim', fgetcsv($handle, 0, ';') ?: fgetcsv($handle, 0, ','));
        $rows    = [];
        $delim   = str_contains(implode('', $headers), ';') ? ';' : ',';

        fclose($handle);
        $handle = fopen($filePath, 'r');

        // Re-lire avec le bon délimiteur
        fgetcsv($handle, 0, $delim); // Sauter les headers

        while (($row = fgetcsv($handle, 0, $delim)) !== false) {
            if (empty(array_filter($row))) continue;
            $mapped = [];
            foreach ($headers as $idx => $header) {
                if ($header) {
                    $val = $row[$idx] ?? null;
                    // Conversion UTF-8 si nécessaire
                    if ($val && !mb_check_encoding($val, 'UTF-8')) {
                        $val = mb_convert_encoding($val, 'UTF-8', 'ISO-8859-1');
                    }
                    $mapped[$header] = $val;
                }
            }
            $rows[] = $mapped;
        }

        fclose($handle);
        return ['headers' => array_filter($headers), 'rows' => $rows];
    }

    private function applyMapping(array $row, array $mapping): array
    {
        $result = [];
        foreach ($mapping as $fileCol => $dbField) {
            if ($dbField && isset($row[$fileCol])) {
                $result[$dbField] = $row[$fileCol];
            }
        }
        // Conserver aussi les colonnes non mappées sous leur nom original
        foreach ($row as $col => $val) {
            if (!isset($result[$col])) {
                $result[$col] = $val;
            }
        }
        return $result;
    }

    private function resolveLocation(array $data, int $campaignId, int $orgId, array &$cache, bool $isDryRun): ?int
    {
        $codeLocal = $data['code_local'] ?? null;
        if (!$codeLocal) {
            return null;
        }

        if (isset($cache[$codeLocal])) {
            return $cache[$codeLocal];
        }

        $location = Database::fetchOne(
            'SELECT id FROM locations WHERE campaign_id = ? AND code_local = ?',
            [$campaignId, $codeLocal]
        );

        if ($location) {
            $cache[$codeLocal] = (int)$location['id'];
            return (int)$location['id'];
        }

        if ($isDryRun) {
            return null;
        }

        // Trouver le site par défaut
        $site = Database::fetchOne('SELECT id FROM sites WHERE campaign_id = ? LIMIT 1', [$campaignId]);

        $locId = Database::insert('locations', [
            'organization_id'   => $orgId,
            'campaign_id'       => $campaignId,
            'site_id'           => $site ? $site['id'] : 0,
            'code_local'        => $codeLocal,
            'designation_local' => $data['designation_local'] ?? $codeLocal,
            'status'            => 'IN_PROGRESS',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        $cache[$codeLocal] = (int)$locId;
        return (int)$locId;
    }

    private function insertItem(array $data, int $campaignId, int $orgId, ?int $locationId, int $jobId, array $config): void
    {
        // Extraire les champs système
        $systemFields = ['code_immo', 'designation', 'serial_number', 'code_local', 'designation_local'];
        $attributes   = [];

        foreach ($data as $key => $val) {
            if (!in_array($key, $systemFields) && !in_array($key, ['organization_id', 'campaign_id', 'location_id'])) {
                $attributes[$key] = $val;
            }
        }

        $siteId = $locationId
            ? (int)(Database::fetchScalar('SELECT site_id FROM locations WHERE id = ?', [$locationId]) ?? 0)
            : 0;

        Database::insert('inventory_items', [
            'organization_id' => $orgId,
            'campaign_id'     => $campaignId,
            'location_id'     => $locationId ?? 0,
            'site_id'         => $siteId,
            'code_immo'       => $data['code_immo'],
            'designation'     => $data['designation'] ?? null,
            'serial_number'   => $data['serial_number'] ?? null,
            'attributes'      => !empty($attributes) ? json_encode($attributes, JSON_UNESCAPED_UNICODE) : null,
            'status'          => 'DRAFT',
            'imported_from'   => $jobId,
            'created_by'      => \App\Core\Auth::id(),
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    private function updateItem(int $itemId, array $data, array $config): void
    {
        $updates = [];
        if (isset($data['designation'])) {
            $updates['designation'] = $data['designation'];
        }
        if (isset($data['serial_number'])) {
            $updates['serial_number'] = $data['serial_number'];
        }

        // Attributs dynamiques
        $existing   = Database::fetchOne('SELECT attributes FROM inventory_items WHERE id = ?', [$itemId]);
        $attributes = json_decode($existing['attributes'] ?? '{}', true) ?: [];
        $systemFields = ['code_immo', 'designation', 'serial_number', 'code_local', 'designation_local'];

        foreach ($data as $key => $val) {
            if (!in_array($key, $systemFields)) {
                $attributes[$key] = $val;
            }
        }

        $updates['attributes'] = json_encode($attributes, JSON_UNESCAPED_UNICODE);
        $updates['updated_at'] = date('Y-m-d H:i:s');
        $updates['updated_by'] = \App\Core\Auth::id();

        if ($updates) {
            Database::update('inventory_items', $updates, ['id' => $itemId]);
        }
    }

    private function determineAction(?string $code, int $campaignId, string $mode, array $errors): string
    {
        if (!empty($errors)) return 'ERROR';
        if (!$code) return 'SKIP';

        $exists = Database::fetchOne('SELECT id FROM inventory_items WHERE campaign_id = ? AND code_immo = ?', [$campaignId, $code]);

        return match($mode) {
            'INSERT' => $exists ? 'SKIP' : 'INSERT',
            'UPDATE' => $exists ? 'UPDATE' : 'SKIP',
            'UPSERT' => $exists ? 'UPDATE' : 'INSERT',
            default  => 'SKIP',
        };
    }
}

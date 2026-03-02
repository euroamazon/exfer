<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\CSRF;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuditService;
use App\Services\ImportService;
use App\Services\ScopeService;

class ImportController extends Controller
{
    private ImportService $importService;

    public function __construct()
    {
        $this->importService = new ImportService();
    }

    public function index(Request $request, array $params): void
    {
        $this->requireAuth();
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);

        $jobs = Database::fetchAll(
            'SELECT j.*, u.name as created_by_name
             FROM import_jobs j LEFT JOIN users u ON u.id = j.created_by
             WHERE j.campaign_id = ? ORDER BY j.created_at DESC',
            [$campaign['id']]
        );

        $this->render('imports/index', [
            'campaign'  => $campaign,
            'jobs'      => $jobs,
            'pageTitle' => 'Import Excel — ' . $campaign['name'],
        ]);
    }

    public function upload(Request $request, array $params): void
    {
        $this->requireAuth();
        CSRF::verify();
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);
        $orgId    = Auth::orgId();

        if (!$request->hasFile('file')) {
            Session::error('Aucun fichier sélectionné.');
            Response::redirect('/campaigns/' . $campaign['id'] . '/import');
        }

        $file = $request->file('file');
        if ($file['error'] !== UPLOAD_ERR_OK) {
            Session::error('Erreur lors de l\'upload.');
            Response::redirect('/campaigns/' . $campaign['id'] . '/import');
        }

        // Vérifier extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
            Session::error('Format non supporté. Utilisez XLSX, XLS ou CSV.');
            Response::redirect('/campaigns/' . $campaign['id'] . '/import');
        }

        // Stocker le fichier
        $uploadDir = STORAGE_PATH . '/uploads/imports';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $filename = 'import_' . time() . '_' . uniqid() . '.' . $ext;
        $filepath = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            Session::error('Impossible de sauvegarder le fichier.');
            Response::redirect('/campaigns/' . $campaign['id'] . '/import');
        }

        // Parser les en-têtes
        try {
            $parsed  = $this->importService->parseFile($filepath);
            $headers = $parsed['headers'];
        } catch (\Throwable $e) {
            unlink($filepath);
            Session::error('Impossible de lire le fichier: ' . $e->getMessage());
            Response::redirect('/campaigns/' . $campaign['id'] . '/import');
        }

        // Créer un job PENDING
        $jobId = Database::insert('import_jobs', [
            'organization_id' => $orgId,
            'campaign_id'     => $campaign['id'],
            'filename'        => $file['name'],
            'filepath'        => $filepath,
            'mode'            => $request->post('mode', 'INSERT'),
            'status'          => 'PENDING',
            'is_dry_run'      => (int)(bool)$request->post('dry_run'),
            'created_by'      => Auth::id(),
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        // Rediriger vers la page de mapping
        Response::redirect('/campaigns/' . $campaign['id'] . '/import/' . $jobId . '/mapping');
    }

    public function mapping(Request $request, array $params): void
    {
        $this->requireAuth();
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);

        $job = Database::fetchOne('SELECT * FROM import_jobs WHERE id = ? AND campaign_id = ?', [(int)$params['jid'], $campaign['id']]);
        if (!$job) Response::notFound();

        // Colonnes disponibles dans la BDD
        $dynamicColumns = Database::fetchAll(
            'SELECT * FROM dynamic_columns WHERE campaign_id = ? AND is_active = 1 ORDER BY position',
            [$campaign['id']]
        );

        $dbFields = ['code_immo' => 'Code Immobilisation *', 'designation' => 'Désignation', 'serial_number' => 'N° Série',
                     'code_local' => 'Code Local', 'designation_local' => 'Désignation Local'];
        foreach ($dynamicColumns as $col) {
            $dbFields[$col['column_key']] = $col['label'];
        }

        // Headers du fichier
        $parsed      = $this->importService->parseFile($job['filepath']);
        $fileHeaders = $parsed['headers'];
        $previewRows = array_slice($parsed['rows'], 0, 5);

        $this->render('imports/mapping', [
            'campaign'    => $campaign,
            'job'         => $job,
            'fileHeaders' => $fileHeaders,
            'dbFields'    => $dbFields,
            'previewRows' => $previewRows,
            'pageTitle'   => 'Mapping colonnes',
        ]);
    }

    public function preview(Request $request, array $params): void
    {
        $this->requireAuth();
        CSRF::verify();
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);
        $orgId    = Auth::orgId();

        $job = Database::fetchOne('SELECT * FROM import_jobs WHERE id = ? AND campaign_id = ?', [(int)$params['jid'], $campaign['id']]);
        if (!$job) Response::notFound();

        // Récupérer le mapping depuis le formulaire (format: mapping[header]=field)
        $rawMapping = $request->post('mapping');
        $mapping    = [];
        if (is_array($rawMapping)) {
            foreach ($rawMapping as $fileCol => $dbField) {
                if ($dbField) {
                    $mapping[$fileCol] = $dbField;
                }
            }
        }

        // Sauvegarder le mapping
        Database::update('import_jobs', ['column_mapping' => json_encode($mapping)], ['id' => $job['id']]);

        // Validation / preview
        $parsed   = $this->importService->parseFile($job['filepath']);
        $isDryRun = (bool)$job['is_dry_run'];
        $result   = $this->importService->validate($parsed['rows'], $mapping, $campaign['id'], $orgId, $job['mode'], $isDryRun);

        $rawStats = $result['stats'] ?? [];
        $stats = [
            'total'    => $rawStats['total']  ?? count($parsed['rows']),
            'valid'    => ($rawStats['insert'] ?? 0) + ($rawStats['update'] ?? 0),
            'errors'   => count($result['errors'] ?? []),
            'warnings' => 0,
        ];

        $this->render('imports/preview', [
            'campaign'    => $campaign,
            'job'         => $job,
            'stats'       => $stats,
            'errors'      => $result['errors'] ?? [],
            'previewRows' => array_map(fn($r) => $r['data'] ?? [], array_slice($result['preview'] ?? [], 0, 10)),
            'pageTitle'   => 'Aperçu import',
        ]);
    }

    public function execute(Request $request, array $params): void
    {
        $this->requireAuth();
        CSRF::verify();
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);

        $job = Database::fetchOne('SELECT * FROM import_jobs WHERE id = ? AND campaign_id = ?', [(int)$params['jid'], $campaign['id']]);
        if (!$job) Response::notFound();

        if ($job['status'] !== 'PENDING') {
            Session::error('Ce job ne peut pas être exécuté (statut: ' . $job['status'] . ').');
            Response::redirect('/campaigns/' . $campaign['id'] . '/import');
        }

        try {
            $result = $this->importService->execute($job['id']);

            $stats   = $result['stats'];
            $message = "Import terminé : {$stats['inserted']} insérés, {$stats['updated']} mis à jour, {$stats['skipped']} ignorés";
            if ($stats['errors'] > 0) {
                $message .= ", {$stats['errors']} erreurs";
            }
            Session::success($message);
        } catch (\Throwable $e) {
            Session::error('Erreur lors de l\'import: ' . $e->getMessage());
        }

        Response::redirect('/campaigns/' . $campaign['id'] . '/import/' . $job['id'] . '/report');
    }

    public function report(Request $request, array $params): void
    {
        $this->requireAuth();
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);

        $job = Database::fetchOne('SELECT * FROM import_jobs WHERE id = ? AND campaign_id = ?', [(int)$params['jid'], $campaign['id']]);
        if (!$job) Response::notFound();

        $stats    = json_decode($job['stats_json'] ?? '{}', true) ?: [];
        $errors   = json_decode($job['error_log'] ?? '[]', true) ?: [];
        $mapping  = json_decode($job['column_mapping'] ?? '{}', true) ?: [];

        $this->render('imports/report', [
            'campaign' => $campaign,
            'job'      => $job,
            'stats'    => $stats,
            'errors'   => $errors,
            'mapping'  => $mapping,
            'pageTitle'=> 'Rapport d\'import',
        ]);
    }
}

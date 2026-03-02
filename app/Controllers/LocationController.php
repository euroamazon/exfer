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
use App\Services\QualityControlService;
use App\Services\ScopeService;

class LocationController extends Controller
{
    public function index(Request $request, array $params): void
    {
        $this->requireAuth();
        $campaign  = ScopeService::requireCampaignAccess((int)$params['cid']);
        $orgId     = Auth::orgId();
        $userId    = Auth::id();

        $siteId = $request->query('site_id');

        $sql    = 'SELECT l.*, s.name as site_name,
                          (SELECT COUNT(*) FROM inventory_items WHERE location_id = l.id) as items_count,
                          (SELECT COUNT(*) FROM anomalies WHERE location_id = l.id AND status IN ("OPEN","INVESTIGATION")) as anomaly_count
                   FROM locations l
                   LEFT JOIN sites s ON s.id = l.site_id
                   WHERE l.campaign_id = ?';
        $params2 = [$campaign['id']];

        if ($siteId) {
            $sql .= ' AND l.site_id = ?';
            $params2[] = $siteId;
        }
        $sql .= ' ORDER BY s.name, l.code_local';

        $locations = Database::fetchAll($sql, $params2);
        $locations = ScopeService::filterLocations($userId, $campaign['id'], $locations, $orgId);

        $sites = Database::fetchAll('SELECT * FROM sites WHERE campaign_id = ? AND is_active = 1 ORDER BY name', [$campaign['id']]);

        $this->render('locations/index', [
            'campaign'  => $campaign,
            'locations' => $locations,
            'sites'     => $sites,
            'siteId'    => $siteId,
            'pageTitle' => 'Locaux — ' . $campaign['name'],
        ]);
    }

    public function create(Request $request, array $params): void
    {
        $this->requireAuth();
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);

        if ($campaign['status'] === 'CLOSED') {
            Session::error('La campagne est clôturée.');
            Response::redirect('/campaigns/' . $campaign['id'] . '/locations');
        }

        $sites = Database::fetchAll('SELECT * FROM sites WHERE campaign_id = ? AND is_active = 1 ORDER BY name', [$campaign['id']]);

        $this->render('locations/form', [
            'campaign'  => $campaign,
            'location'  => null,
            'sites'     => $sites,
            'pageTitle' => 'Nouveau local',
        ]);
    }

    public function store(Request $request, array $params): void
    {
        $this->requireAuth();
        CSRF::verify();
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);
        $orgId    = Auth::orgId();

        if ($campaign['status'] === 'CLOSED') {
            Response::forbidden('Campagne clôturée.');
        }

        $errors = $request->validate([
            'code_local' => 'required|max:100',
        ]);

        if ($errors) {
            Session::flash('errors', $errors);
            Session::flashOld($request->allPost());
            Response::redirect('/campaigns/' . $campaign['id'] . '/locations/create');
        }

        // Vérifier unicité
        $existing = Database::fetchOne(
            'SELECT id FROM locations WHERE organization_id = ? AND campaign_id = ? AND code_local = ?',
            [$orgId, $campaign['id'], $request->post('code_local')]
        );

        if ($existing) {
            Session::error('Ce code local existe déjà dans cette campagne.');
            Session::flashOld($request->allPost());
            Response::redirect('/campaigns/' . $campaign['id'] . '/locations/create');
        }

        $siteId = $request->post('site_id') ? (int)$request->post('site_id') : null;

        $id = Database::insert('locations', [
            'organization_id'   => $orgId,
            'campaign_id'       => $campaign['id'],
            'site_id'           => $siteId,
            'code_local'        => $request->post('code_local'),
            'designation_local' => $request->post('designation_local') ?: null,
            'status'            => 'IN_PROGRESS',
            'created_by'        => Auth::id(),
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        AuditService::log('CREATE_LOCATION', 'location', (int)$id, null, [
            'code_local' => $request->post('code_local'),
            'campaign_id'=> $campaign['id'],
        ]);

        Session::success('Local créé. Vous pouvez maintenant saisir les immobilisations.');
        Response::redirect('/campaigns/' . $campaign['id'] . '/locations/' . $id . '/spreadsheet');
    }

    public function spreadsheet(Request $request, array $params): void
    {
        $this->requireAuth();
        $location = ScopeService::requireLocationAccess((int)$params['lid']);
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);
        $orgId    = Auth::orgId();

        // Colonnes dynamiques
        $columns = Database::fetchAll(
            'SELECT * FROM dynamic_columns WHERE campaign_id = ? AND is_active = 1 ORDER BY position',
            [$campaign['id']]
        );

        // Items du local
        $items = Database::fetchAll(
            'SELECT i.*, u.name as created_by_name
             FROM inventory_items i
             LEFT JOIN users u ON u.id = i.created_by
             WHERE i.location_id = ?
             ORDER BY i.code_immo',
            [$location['id']]
        );

        // Config campagne
        $config = json_decode($campaign['config'] ?? '{}', true) ?: [];

        // Rouleau ouvert de l'agent
        $openRoll = null;
        if (!empty($config['label_roll_enabled'])) {
            $openRoll = Database::fetchOne(
                'SELECT * FROM label_rolls WHERE campaign_id = ? AND agent_id = ? AND status = "OPEN"',
                [$campaign['id'], Auth::id()]
            );
        }

        // Anomalies du local
        $anomalies = Database::fetchAll(
            'SELECT * FROM anomalies WHERE location_id = ? AND status IN ("OPEN","INVESTIGATION") ORDER BY severity DESC',
            [$location['id']]
        );

        // Suggestion du prochain code
        $suggestedCode = null;
        if (!Auth::isAdmin() || true) {
            $suggestedCode = \App\Services\CodeValidationService::suggestNextCode(
                $campaign['id'], $orgId, $config
            );
        }

        $this->render('locations/spreadsheet', [
            'campaign'     => $campaign,
            'location'     => $location,
            'columns'      => $columns,
            'items'        => $items,
            'config'       => $config,
            'openRoll'     => $openRoll,
            'anomalies'    => $anomalies,
            'suggestedCode'=> $suggestedCode,
            'pageTitle'    => 'Saisie — Local ' . $location['code_local'],
        ]);
    }

    public function validate(Request $request, array $params): void
    {
        $this->requireAuth();
        CSRF::verify();
        $location = ScopeService::requireLocationAccess((int)$params['lid']);
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);

        if ($campaign['status'] === 'CLOSED') {
            Response::forbidden('Campagne clôturée.');
        }

        $qcService = new QualityControlService();
        $result    = $qcService->runLocationChecks($location['id']);

        if ($result['has_blocking']) {
            if ($request->isAjax()) {
                $this->jsonError('Des anomalies bloquantes empêchent la validation.', 422, [
                    'blocking_anomalies' => $result['blocking_anomalies'],
                    'checks' => $result['checks'],
                ]);
            }
            Session::error('Des anomalies bloquantes empêchent la validation. Veuillez les résoudre d\'abord.');
            Response::redirect('/campaigns/' . $campaign['id'] . '/locations/' . $location['id'] . '/spreadsheet');
        }

        // Valider le local
        $old = ['status' => $location['status']];
        Database::update('locations', [
            'status'       => 'VALIDATED',
            'validated_at' => date('Y-m-d H:i:s'),
            'validated_by' => Auth::id(),
            'updated_at'   => date('Y-m-d H:i:s'),
        ], ['id' => $location['id']]);

        AuditService::log('VALIDATE_LOCATION', 'location', $location['id'], $old, ['status' => 'VALIDATED']);

        if ($request->isAjax()) {
            $this->jsonSuccess(['status' => 'VALIDATED'], 'Local validé avec succès.');
        }

        Session::success('Local validé avec succès. ' . $result['items_checked'] . ' article(s) contrôlé(s).');
        Response::redirect('/campaigns/' . $campaign['id'] . '/locations');
    }

    public function reopen(Request $request, array $params): void
    {
        $this->requireAuth();
        CSRF::verify();
        $location = ScopeService::requireLocationAccess((int)$params['lid']);
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);

        if ($campaign['status'] === 'CLOSED') {
            Response::forbidden('Campagne clôturée.');
        }

        $old = ['status' => $location['status']];
        Database::update('locations', [
            'status'       => 'NEEDS_REVIEW',
            'validated_at' => null,
            'validated_by' => null,
            'updated_at'   => date('Y-m-d H:i:s'),
        ], ['id' => $location['id']]);

        AuditService::log('REOPEN_LOCATION', 'location', $location['id'], $old, ['status' => 'NEEDS_REVIEW']);
        Session::success('Local réouvert pour révision.');
        Response::redirect('/campaigns/' . $campaign['id'] . '/locations/' . $location['id'] . '/spreadsheet');
    }
}

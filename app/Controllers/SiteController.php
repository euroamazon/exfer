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
use App\Services\ScopeService;

class SiteController extends Controller
{
    public function index(Request $request, array $params): void
    {
        $this->requireAuth();
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);

        $sites = Database::fetchAll(
            'SELECT s.*,
                    (SELECT COUNT(*) FROM locations WHERE site_id = s.id) as location_count,
                    (SELECT COUNT(*) FROM inventory_items WHERE site_id = s.id) as items_count
             FROM sites s WHERE s.campaign_id = ? ORDER BY s.name',
            [$campaign['id']]
        );

        $this->render('sites/index', [
            'campaign'  => $campaign,
            'sites'     => $sites,
            'pageTitle' => 'Sites — ' . $campaign['name'],
        ]);
    }

    public function store(Request $request, array $params): void
    {
        $this->requireRole('SUPERVISEUR');
        CSRF::verify();
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);
        $orgId    = Auth::orgId();

        $errors = $request->validate(['name' => 'required|max:255']);
        if ($errors) {
            Session::flash('errors', $errors);
            Response::redirect('/campaigns/' . $campaign['id'] . '/sites');
        }

        $id = Database::insert('sites', [
            'organization_id' => $orgId,
            'campaign_id'     => $campaign['id'],
            'name'            => $request->post('name'),
            'code'            => $request->post('code') ?: null,
            'address'         => $request->post('address') ?: null,
            'is_active'       => 1,
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        AuditService::log('CREATE_SITE', 'site', (int)$id, null, ['name' => $request->post('name')]);
        Session::success('Site créé avec succès.');
        Response::redirect('/campaigns/' . $campaign['id'] . '/sites');
    }

    public function update(Request $request, array $params): void
    {
        $this->requireRole('SUPERVISEUR');
        CSRF::verify();
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);
        $orgId    = Auth::orgId();

        $site = Database::fetchOne('SELECT * FROM sites WHERE id = ? AND campaign_id = ?', [(int)$params['sid'], $campaign['id']]);
        if (!$site) Response::notFound();

        Database::update('sites', [
            'name'       => $request->post('name'),
            'code'       => $request->post('code') ?: null,
            'address'    => $request->post('address') ?: null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $site['id']]);

        AuditService::log('UPDATE_SITE', 'site', $site['id']);
        if ($request->isAjax()) {
            $this->jsonSuccess(null, 'Site mis à jour.');
        }
        Session::success('Site mis à jour.');
        Response::redirect('/campaigns/' . $campaign['id'] . '/sites');
    }

    public function delete(Request $request, array $params): void
    {
        $this->requireRole('SUPERVISEUR');
        CSRF::verify();
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);

        $site = Database::fetchOne('SELECT * FROM sites WHERE id = ? AND campaign_id = ?', [(int)$params['sid'], $campaign['id']]);
        if (!$site) Response::notFound();

        // Vérifier s'il y a des locaux
        $locCount = Database::fetchScalar('SELECT COUNT(*) FROM locations WHERE site_id = ?', [$site['id']]);
        if ($locCount > 0) {
            Session::error("Impossible de supprimer ce site : il contient {$locCount} local/locaux.");
            Response::redirect('/campaigns/' . $campaign['id'] . '/sites');
        }

        Database::query('DELETE FROM sites WHERE id = ?', [$site['id']]);
        AuditService::log('DELETE_SITE', 'site', $site['id']);
        Session::success('Site supprimé.');
        Response::redirect('/campaigns/' . $campaign['id'] . '/sites');
    }
}

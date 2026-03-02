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

class ServiceController extends Controller
{
    public function index(Request $request): void
    {
        $this->requireRole('SUPERVISEUR');
        $orgId    = Auth::orgId();
        $services = Database::fetchAll(
            'SELECT s.*, (SELECT COUNT(*) FROM campaigns WHERE service_id = s.id) as campaign_count
             FROM services s WHERE s.organization_id = ? ORDER BY s.name',
            [$orgId]
        );
        $this->render('services/index', ['services' => $services, 'pageTitle' => 'Prestations']);
    }

    public function store(Request $request): void
    {
        $this->requireRole('SUPERVISEUR');
        CSRF::verify();
        $orgId = Auth::orgId();

        $errors = $request->validate(['name' => 'required|max:255']);
        if ($errors) {
            Session::flash('errors', $errors);
            Response::redirect('/services');
        }

        $id = Database::insert('services', [
            'organization_id' => $orgId,
            'name'            => $request->post('name'),
            'code'            => $request->post('code') ?: null,
            'description'     => $request->post('description') ?: null,
            'is_active'       => 1,
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        AuditService::log('CREATE_SERVICE', 'service', (int)$id);
        Session::success('Prestation créée.');
        Response::redirect('/services');
    }

    public function update(Request $request, array $params): void
    {
        $this->requireRole('SUPERVISEUR');
        CSRF::verify();
        $orgId   = Auth::orgId();
        $service = Database::fetchOne('SELECT * FROM services WHERE id = ? AND organization_id = ?', [(int)$params['id'], $orgId]);
        if (!$service) Response::notFound();

        Database::update('services', [
            'name'        => $request->post('name'),
            'code'        => $request->post('code') ?: null,
            'description' => $request->post('description') ?: null,
            'is_active'   => (int)(bool)$request->post('is_active', 1),
            'updated_at'  => date('Y-m-d H:i:s'),
        ], ['id' => $service['id']]);

        AuditService::log('UPDATE_SERVICE', 'service', $service['id']);
        Session::success('Prestation mise à jour.');
        Response::redirect('/services');
    }

    public function delete(Request $request, array $params): void
    {
        $this->requireRole('SUPERVISEUR');
        CSRF::verify();
        $orgId   = Auth::orgId();
        $service = Database::fetchOne('SELECT * FROM services WHERE id = ? AND organization_id = ?', [(int)$params['id'], $orgId]);
        if (!$service) Response::notFound();

        $count = Database::fetchScalar('SELECT COUNT(*) FROM campaigns WHERE service_id = ?', [$service['id']]);
        if ($count > 0) {
            Session::error("Impossible de supprimer cette prestation : {$count} campagne(s) y sont associées.");
            Response::redirect('/services');
        }

        Database::query('DELETE FROM services WHERE id = ?', [$service['id']]);
        AuditService::log('DELETE_SERVICE', 'service', $service['id']);
        Session::success('Prestation supprimée.');
        Response::redirect('/services');
    }
}

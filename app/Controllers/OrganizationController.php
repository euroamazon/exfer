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

class OrganizationController extends Controller
{
    public function index(Request $request): void
    {
        $this->requireAdmin();
        $orgs = Database::fetchAll(
            'SELECT o.*, (SELECT COUNT(*) FROM users WHERE organization_id = o.id) as user_count FROM organizations o ORDER BY o.name'
        );
        $this->render('organizations/index', ['organizations' => $orgs, 'pageTitle' => 'Organisations']);
    }

    public function create(Request $request): void
    {
        $this->requireAdmin();
        $this->render('organizations/form', ['org' => null, 'pageTitle' => 'Nouvelle organisation']);
    }

    public function store(Request $request): void
    {
        $this->requireAdmin();
        CSRF::verify();

        $errors = $request->validate(['name' => 'required|max:255']);
        if ($errors) {
            Session::flash('errors', $errors);
            Response::redirect('/admin/organizations/create');
        }

        $slug = self::slugify($request->post('name'));
        $existing = Database::fetchOne('SELECT id FROM organizations WHERE slug = ?', [$slug]);
        if ($existing) {
            $slug .= '_' . time();
        }

        $id = Database::insert('organizations', [
            'name'       => $request->post('name'),
            'slug'       => $slug,
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        AuditService::log('CREATE_ORGANIZATION', 'organization', (int)$id);
        Session::success('Organisation créée.');
        Response::redirect('/admin/organizations');
    }

    public function edit(Request $request, array $params): void
    {
        $this->requireAdmin();
        $org = Database::fetchOne('SELECT * FROM organizations WHERE id = ?', [(int)$params['id']]);
        if (!$org) Response::notFound();
        $this->render('organizations/form', ['org' => $org, 'pageTitle' => 'Modifier l\'organisation']);
    }

    public function update(Request $request, array $params): void
    {
        $this->requireAdmin();
        CSRF::verify();

        $org = Database::fetchOne('SELECT * FROM organizations WHERE id = ?', [(int)$params['id']]);
        if (!$org) Response::notFound();

        $errors = $request->validate(['name' => 'required|max:255']);
        if ($errors) {
            Session::flash('errors', $errors);
            Response::redirect('/admin/organizations/' . $org['id'] . '/edit');
        }

        Database::update('organizations', [
            'name'       => $request->post('name'),
            'is_active'  => (int)(bool)$request->post('is_active'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $org['id']]);

        AuditService::log('UPDATE_ORGANIZATION', 'organization', $org['id']);
        Session::success('Organisation mise à jour.');
        Response::redirect('/admin/organizations');
    }

    private static function slugify(string $text): string
    {
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII; [^a-zA-Z0-9] Lower()', $text) ?: strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '_', $text);
        return trim($text, '_');
    }
}

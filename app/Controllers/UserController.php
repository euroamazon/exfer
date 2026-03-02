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
use App\Services\AuthService;

class UserController extends Controller
{
    public function index(Request $request): void
    {
        $this->requireRole('SUPERVISEUR');
        $orgId = Auth::orgId();

        $users = Database::fetchAll(
            'SELECT u.*, (SELECT COUNT(*) FROM campaign_members WHERE user_id = u.id AND is_active = 1) as campaign_count
             FROM users u WHERE u.organization_id = ? ORDER BY u.name',
            [$orgId]
        );

        $this->render('users/index', ['users' => $users, 'pageTitle' => 'Utilisateurs']);
    }

    public function create(Request $request): void
    {
        $this->requireRole('SUPERVISEUR');
        $this->render('users/form', ['user' => null, 'pageTitle' => 'Nouvel utilisateur']);
    }

    public function store(Request $request): void
    {
        $this->requireRole('SUPERVISEUR');
        CSRF::verify();
        $orgId = Auth::orgId();

        $errors = $request->validate([
            'name'     => 'required|max:255',
            'email'    => 'required|email|max:255',
            'password' => 'required|min:8',
            'role'     => 'required|in:ADMIN,SUPERVISEUR,AGENT',
        ]);

        // Seul un ADMIN peut créer un autre ADMIN
        if ($request->post('role') === 'ADMIN' && !Auth::isAdmin()) {
            $errors['role'][] = 'Seul un administrateur peut créer un compte ADMIN.';
        }

        // Vérifier unicité email
        $emailLower = strtolower(trim($request->post('email', '')));
        $existing   = Database::fetchOne('SELECT id FROM users WHERE email = ?', [$emailLower]);
        if ($existing) {
            $errors['email'][] = 'Cette adresse email est déjà utilisée.';
        }

        if ($errors) {
            Session::flash('errors', $errors);
            Session::flashOld($request->allPost());
            Response::redirect('/users/create');
        }

        $id = Database::insert('users', [
            'organization_id' => $orgId,
            'name'            => $request->post('name'),
            'email'           => $emailLower,
            'password_hash'   => AuthService::hashPassword($request->post('password')),
            'role'            => $request->post('role'),
            'is_active'       => 1,
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        AuditService::log('CREATE_USER', 'user', (int)$id, null, ['email' => $emailLower, 'role' => $request->post('role')]);
        Session::success('Utilisateur créé avec succès.');
        Response::redirect('/users');
    }

    public function edit(Request $request, array $params): void
    {
        $this->requireRole('SUPERVISEUR');
        $orgId = Auth::orgId();

        $user = Database::fetchOne('SELECT * FROM users WHERE id = ? AND organization_id = ?', [(int)$params['id'], $orgId]);
        if (!$user) Response::notFound();

        $this->render('users/form', ['user' => $user, 'pageTitle' => 'Modifier l\'utilisateur']);
    }

    public function update(Request $request, array $params): void
    {
        $this->requireRole('SUPERVISEUR');
        CSRF::verify();
        $orgId = Auth::orgId();

        $user = Database::fetchOne('SELECT * FROM users WHERE id = ? AND organization_id = ?', [(int)$params['id'], $orgId]);
        if (!$user) Response::notFound();

        $errors = $request->validate([
            'name'  => 'required|max:255',
            'email' => 'required|email|max:255',
            'role'  => 'required|in:ADMIN,SUPERVISEUR,AGENT',
        ]);

        if ($request->post('role') === 'ADMIN' && !Auth::isAdmin()) {
            $errors['role'][] = 'Seul un administrateur peut attribuer le rôle ADMIN.';
        }

        $emailLower = strtolower(trim($request->post('email', '')));
        $existing   = Database::fetchOne('SELECT id FROM users WHERE email = ? AND id != ?', [$emailLower, $user['id']]);
        if ($existing) {
            $errors['email'][] = 'Cette adresse email est déjà utilisée.';
        }

        if ($errors) {
            Session::flash('errors', $errors);
            Response::redirect('/users/' . $user['id'] . '/edit');
        }

        $updates = [
            'name'       => $request->post('name'),
            'email'      => $emailLower,
            'role'       => $request->post('role'),
            'is_active'  => (int)(bool)$request->post('is_active', 1),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Changer le mot de passe si fourni
        $newPwd = $request->post('password');
        if ($newPwd) {
            if (strlen($newPwd) < 8) {
                Session::error('Le mot de passe doit faire au moins 8 caractères.');
                Response::redirect('/users/' . $user['id'] . '/edit');
            }
            $updates['password_hash'] = AuthService::hashPassword($newPwd);
        }

        Database::update('users', $updates, ['id' => $user['id']]);
        AuditService::log('UPDATE_USER', 'user', $user['id']);
        Session::success('Utilisateur mis à jour.');
        Response::redirect('/users');
    }

    public function profile(Request $request): void
    {
        $this->requireAuth();
        $user = Database::fetchOne('SELECT * FROM users WHERE id = ?', [Auth::id()]);
        $this->render('users/profile', ['user' => $user, 'pageTitle' => 'Mon profil']);
    }

    public function updateProfile(Request $request): void
    {
        $this->requireAuth();
        CSRF::verify();

        $user   = Database::fetchOne('SELECT * FROM users WHERE id = ?', [Auth::id()]);
        $errors = $request->validate(['name' => 'required|max:255']);

        if ($errors) {
            Session::flash('errors', $errors);
            Response::redirect('/profile');
        }

        $updates = ['name' => $request->post('name'), 'updated_at' => date('Y-m-d H:i:s')];

        $newPwd = $request->post('password');
        if ($newPwd) {
            if (strlen($newPwd) < 8) {
                Session::error('Le mot de passe doit faire au moins 8 caractères.');
                Response::redirect('/profile');
            }
            if ($newPwd !== $request->post('password_confirm')) {
                Session::error('Les mots de passe ne correspondent pas.');
                Response::redirect('/profile');
            }
            $updates['password_hash'] = AuthService::hashPassword($newPwd);
        }

        Database::update('users', $updates, ['id' => Auth::id()]);

        // Mettre à jour la session
        $_SESSION['_user']['name'] = $updates['name'];

        AuditService::log('UPDATE_PROFILE', 'user', Auth::id());
        Session::success('Profil mis à jour.');
        Response::redirect('/profile');
    }
}

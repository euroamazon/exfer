<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\CSRF;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuthService;

class AuthController extends Controller
{
    public function loginForm(Request $request): void
    {
        if (Auth::check()) {
            Response::redirect('/dashboard');
        }
        $this->render('auth/login', [], null); // Sans layout
    }

    public function login(Request $request): void
    {
        CSRF::verify();

        $email    = trim($request->post('email', ''));
        $password = $request->post('password', '');

        $errors = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if ($errors) {
            Session::flashOld(['email' => $email]);
            Session::flash('errors', $errors);
            Response::redirect('/login');
        }

        $result = AuthService::attempt($email, $password, $request->ip());

        if (!$result['success']) {
            Session::error($result['error']);
            Session::flashOld(['email' => $email]);
            Response::redirect('/login');
        }

        // Rediriger vers la page demandée ou le dashboard
        $redirect = Session::flash('redirect_after_login') ?? '/dashboard';
        Response::redirect($redirect);
    }

    public function logout(Request $request): void
    {
        CSRF::verify();
        AuthService::logout();
        Session::success('Vous avez été déconnecté avec succès.');
        Response::redirect('/login');
    }
}

<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/**
 * Middleware d'authentification
 * Vérifie que l'utilisateur est connecté avant d'accéder à la route.
 */
class AuthMiddleware
{
    public function handle(Request $request): void
    {
        if (!Auth::check()) {
            if ($request->isAjax()) {
                Response::json(['success' => false, 'message' => 'Non authentifié.'], 401);
            }
            Response::redirect('/login?redirect=' . urlencode($request->uri()));
        }
    }
}

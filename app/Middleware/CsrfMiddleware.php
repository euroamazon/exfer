<?php

namespace App\Middleware;

use App\Core\CSRF;
use App\Core\Request;

/**
 * Middleware de protection CSRF
 * Vérifie le token CSRF sur toutes les requêtes mutantes (POST/PUT/DELETE/PATCH).
 */
class CsrfMiddleware
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request): void
    {
        if (in_array($request->method(), self::SAFE_METHODS, true)) {
            return;
        }

        CSRF::verify($request);
    }
}

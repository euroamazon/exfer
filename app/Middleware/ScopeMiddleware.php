<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\ScopeService;

/**
 * Middleware de vérification du périmètre (scope) de campagne.
 *
 * Appliqué aux routes /campaigns/{cid}/* pour s'assurer que l'utilisateur
 * a bien accès à la campagne demandée.
 * Les administrateurs ont accès à toutes les campagnes de leur organisation.
 */
class ScopeMiddleware
{
    private ScopeService $scopeService;

    public function __construct()
    {
        $this->scopeService = new ScopeService();
    }

    public function handle(Request $request): void
    {
        if (!Auth::check()) {
            return; // AuthMiddleware s'en charge
        }

        // Extraire campaign id depuis les paramètres de route
        $campaignId = $request->param('cid') ?? $request->param('id');

        if (!$campaignId) {
            return; // Pas de campagne dans la route
        }

        $userId = Auth::id();
        $orgId  = Auth::orgId();

        if (!$this->scopeService->checkCampaignAccess($userId, (int)$campaignId, $orgId)) {
            if ($request->isAjax()) {
                Response::json(['success' => false, 'message' => 'Accès refusé à cette campagne.'], 403);
            }
            Response::forbidden('Vous n\'avez pas accès à cette campagne.');
        }
    }
}

<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\CSRF;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AnomalyService;
use App\Services\ScopeService;

class AnomalyController extends Controller
{
    private AnomalyService $anomalyService;

    public function __construct()
    {
        $this->anomalyService = new AnomalyService();
    }

    public function index(Request $request, array $params): void
    {
        $this->requireAuth();
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);

        $filters = [
            'type'        => $request->query('type') ?: null,
            'status'      => $request->query('status') ?: null,
            'severity'    => $request->query('severity') ?: null,
            'location_id' => $request->query('location_id') ? (int)$request->query('location_id') : null,
        ];

        $anomalies = $this->anomalyService->getByCampaign($campaign['id'], array_filter($filters));
        $rawStats  = $this->anomalyService->getStats($campaign['id']);

        // Comptes par statut pour les cards
        $stats = ['OPEN' => 0, 'INVESTIGATION' => 0, 'RESOLVED' => 0, 'REJECTED' => 0];
        foreach ($rawStats as $s) {
            $stats[$s['status']] = ($stats[$s['status']] ?? 0) + (int)$s['count'];
        }

        $this->render('anomalies/index', [
            'campaign'  => $campaign,
            'anomalies' => $anomalies,
            'stats'     => $stats,
            'filters'   => $filters,
            'pageTitle' => 'Anomalies — ' . $campaign['name'],
        ]);
    }

    public function investigate(Request $request, array $params): void
    {
        $this->requireAuth();
        CSRF::verify();

        // Le modal envoie le champ "notes"
        $notes = $request->post('notes', '');
        try {
            $this->anomalyService->investigate((int)$params['id'], $notes);
            Session::success('Anomalie passée en investigation.');
        } catch (\Throwable $e) {
            Session::error($e->getMessage());
        }

        Response::back('/');
    }

    public function resolve(Request $request, array $params): void
    {
        $this->requireAuth();
        CSRF::verify();

        $notes = $request->post('notes', '');
        try {
            $this->anomalyService->resolve((int)$params['id'], $notes);
            Session::success('Anomalie résolue.');
        } catch (\Throwable $e) {
            Session::error($e->getMessage());
        }

        Response::back('/');
    }

    public function reject(Request $request, array $params): void
    {
        $this->requireAuth();
        CSRF::verify();

        $notes = $request->post('notes', '');
        try {
            $this->anomalyService->reject((int)$params['id'], $notes);
            Session::success('Anomalie rejetée.');
        } catch (\Throwable $e) {
            Session::error($e->getMessage());
        }

        Response::back('/');
    }
}

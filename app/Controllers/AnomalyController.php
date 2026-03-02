<?php

namespace App\Controllers;

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
            'type'     => $request->query('type'),
            'status'   => $request->query('status'),
            'severity' => $request->query('severity'),
        ];

        $anomalies = $this->anomalyService->getByCampaign($campaign['id'], array_filter($filters));
        $stats     = $this->anomalyService->getStats($campaign['id']);

        // Organiser les stats
        $statsByType = [];
        foreach ($stats as $s) {
            $statsByType[$s['type']][$s['status']] = (int)$s['count'];
        }

        $this->render('anomalies/index', [
            'campaign'    => $campaign,
            'anomalies'   => $anomalies,
            'statsByType' => $statsByType,
            'filters'     => $filters,
            'pageTitle'   => 'Anomalies — ' . $campaign['name'],
        ]);
    }

    public function investigate(Request $request, array $params): void
    {
        $this->requireAuth();
        CSRF::verify();

        $notes = $request->post('investigation_notes', '');
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

        $notes = $request->post('resolution_notes', '');
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

        $notes = $request->post('rejection_notes', '');
        try {
            $this->anomalyService->reject((int)$params['id'], $notes);
            Session::success('Anomalie rejetée.');
        } catch (\Throwable $e) {
            Session::error($e->getMessage());
        }

        Response::back('/');
    }
}

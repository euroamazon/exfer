<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\CSRF;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AnomalyService;
use App\Services\LabelRollService;
use App\Services\ScopeService;

class LabelRollController extends Controller
{
    public function index(Request $request, array $params): void
    {
        $this->requireAuth();
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);

        $rolls = Database::fetchAll(
            'SELECT lr.*, u.name as agent_name
             FROM label_rolls lr
             JOIN users u ON u.id = lr.agent_id
             WHERE lr.campaign_id = ?
             ORDER BY lr.created_at DESC',
            [$campaign['id']]
        );

        $this->render('label_rolls/index', [
            'campaign'  => $campaign,
            'rolls'     => $rolls,
            'pageTitle' => 'Rouleaux d\'étiquettes',
        ]);
    }

    public function store(Request $request, array $params): void
    {
        $this->requireAuth();
        CSRF::verify();
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);
        $orgId    = Auth::orgId();
        $config   = json_decode($campaign['config'] ?? '{}', true) ?: [];

        if ($campaign['status'] !== 'ACTIVE') {
            Session::error('La campagne doit être active pour créer un rouleau.');
            Response::redirect('/campaigns/' . $campaign['id'] . '/label-rolls');
        }

        $errors = $request->validate([
            'start_code' => 'required',
            'end_code'   => 'required',
        ]);

        // Quel agent ?
        $agentId = Auth::isAdmin() || Auth::isSuperviseur()
            ? ((int)$request->post('agent_id') ?: Auth::id())
            : Auth::id();

        if ($errors) {
            Session::flash('errors', $errors);
            Response::redirect('/campaigns/' . $campaign['id'] . '/label-rolls');
        }

        try {
            LabelRollService::create(
                $orgId,
                $campaign['id'],
                $agentId,
                $request->post('start_code'),
                $request->post('end_code'),
                $config
            );
            Session::success('Rouleau créé avec succès.');
        } catch (\Throwable $e) {
            Session::error($e->getMessage());
        }

        Response::redirect('/campaigns/' . $campaign['id'] . '/label-rolls');
    }

    public function skipCode(Request $request, array $params): void
    {
        $this->requireAuth();
        CSRF::verify();
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);

        $roll = Database::fetchOne('SELECT * FROM label_rolls WHERE id = ? AND campaign_id = ?', [(int)$params['rid'], $campaign['id']]);
        if (!$roll) {
            Response::notFound('Rouleau introuvable.');
        }

        // Vérifier que l'agent est propriétaire du rouleau (ou superviseur)
        if (!Auth::isSuperviseur() && $roll['agent_id'] != Auth::id()) {
            Response::forbidden("Ce rouleau ne vous appartient pas.");
        }

        $reason = $request->post('reason', 'LOST');
        if (!in_array($reason, ['LOST', 'DAMAGED'])) {
            $reason = 'LOST';
        }

        try {
            $skipped = LabelRollService::skipCode((int)$params['rid'], $reason, new AnomalyService());

            if ($request->isAjax()) {
                $roll = Database::fetchOne('SELECT * FROM label_rolls WHERE id = ?', [(int)$params['rid']]);
                $this->jsonSuccess(['roll' => $roll, 'skipped_code' => $skipped], "Code {$skipped} sauté.");
            }

            Session::success("Code {$skipped} marqué comme {$reason}.");
        } catch (\Throwable $e) {
            if ($request->isAjax()) {
                $this->jsonError($e->getMessage(), 422);
            }
            Session::error($e->getMessage());
        }

        Response::redirect('/campaigns/' . $campaign['id'] . '/label-rolls');
    }

    public function close(Request $request, array $params): void
    {
        $this->requireAuth();
        CSRF::verify();
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);

        $roll = Database::fetchOne('SELECT * FROM label_rolls WHERE id = ? AND campaign_id = ?', [(int)$params['rid'], $campaign['id']]);
        if (!$roll) {
            Response::notFound('Rouleau introuvable.');
        }

        if (!Auth::isSuperviseur() && $roll['agent_id'] != Auth::id()) {
            Response::forbidden();
        }

        try {
            $summary = LabelRollService::closeRoll((int)$params['rid']);
            Session::success("Rouleau clôturé. {$summary['used']} codes utilisés, {$summary['skipped']} sautés.");
        } catch (\Throwable $e) {
            Session::error($e->getMessage());
        }

        Response::redirect('/campaigns/' . $campaign['id'] . '/label-rolls');
    }
}

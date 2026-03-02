<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Services\ScopeService;

class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $this->requireAuth();

        $userId = Auth::id();
        $orgId  = Auth::orgId();

        // Campagnes accessibles
        $campaigns = ScopeService::userCampaigns($userId, $orgId);

        // Stats globales
        $stats = [
            'campaigns_active'  => Database::fetchScalar(
                'SELECT COUNT(*) FROM campaigns WHERE organization_id = ? AND status = "ACTIVE"', [$orgId]
            ),
            'items_total'  => Database::fetchScalar(
                'SELECT COUNT(*) FROM inventory_items WHERE organization_id = ?', [$orgId]
            ),
            'anomalies_open' => Database::fetchScalar(
                'SELECT COUNT(*) FROM anomalies WHERE organization_id = ? AND status IN ("OPEN","INVESTIGATION")', [$orgId]
            ),
            'users_active' => Database::fetchScalar(
                'SELECT COUNT(*) FROM users WHERE organization_id = ? AND is_active = 1', [$orgId]
            ),
        ];

        // Avancement par campagne active
        $campaignProgress = [];
        foreach ($campaigns as $campaign) {
            if ($campaign['status'] !== 'ACTIVE') continue;

            $totalLocations     = Database::fetchScalar(
                'SELECT COUNT(*) FROM locations WHERE campaign_id = ?', [$campaign['id']]
            );
            $validatedLocations = Database::fetchScalar(
                'SELECT COUNT(*) FROM locations WHERE campaign_id = ? AND status = "VALIDATED"', [$campaign['id']]
            );
            $totalItems = Database::fetchScalar(
                'SELECT COUNT(*) FROM inventory_items WHERE campaign_id = ?', [$campaign['id']]
            );
            $openAnomalies = Database::fetchScalar(
                'SELECT COUNT(*) FROM anomalies WHERE campaign_id = ? AND status IN ("OPEN","INVESTIGATION")', [$campaign['id']]
            );

            $progress = $totalLocations > 0
                ? round(($validatedLocations / $totalLocations) * 100)
                : 0;

            $campaignProgress[] = [
                'campaign'            => $campaign,
                'total_locations'     => $totalLocations,
                'validated_locations' => $validatedLocations,
                'total_items'         => $totalItems,
                'open_anomalies'      => $openAnomalies,
                'progress'            => $progress,
            ];
        }

        // Activité récente (audit)
        $recentActivity = Database::fetchAll(
            'SELECT al.*, u.name as user_name
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.organization_id = ?
             ORDER BY al.created_at DESC
             LIMIT 10',
            [$orgId]
        );

        $this->render('dashboard/index', [
            'stats'            => $stats,
            'campaigns'        => $campaigns,
            'campaignProgress' => $campaignProgress,
            'recentActivity'   => $recentActivity,
            'pageTitle'        => 'Tableau de bord',
        ]);
    }
}

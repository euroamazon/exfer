<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\ExportService;
use App\Services\ScopeService;

class ExportController extends Controller
{
    private ExportService $exportService;

    public function __construct()
    {
        $this->exportService = new ExportService();
    }

    public function export(Request $request, array $params): void
    {
        $this->requireAuth();
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);

        $format  = $request->query('format', 'csv');
        $type    = $request->query('type', 'inventory');
        $filters = [
            'site_id'     => $request->query('site_id'),
            'location_id' => $request->query('location_id'),
            'status'      => $request->query('status'),
        ];

        $campaignName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $campaign['name']);
        $date         = date('Y-m-d');

        switch ($format) {
            case 'xlsx':
                $tmpFile = $this->exportService->toXLSX($campaign['id'], array_filter($filters));
                Response::download($tmpFile, "inventaire_{$campaignName}_{$date}.xlsx", 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                unlink($tmpFile);
                break;

            case 'pdf':
                $tmpFile = $this->exportService->toPDF($type, $campaign['id'], array_filter($filters));
                Response::download($tmpFile, "pv_{$type}_{$campaignName}_{$date}.pdf", 'application/pdf');
                unlink($tmpFile);
                break;

            case 'anomalies_csv':
                $content = $this->exportService->anomaliesToCSV($campaign['id']);
                Response::streamDownload($content, "anomalies_{$campaignName}_{$date}.csv", 'text/csv; charset=utf-8');
                break;

            case 'csv':
            default:
                $content = $this->exportService->toCSV($campaign['id'], array_filter($filters));
                Response::streamDownload($content, "inventaire_{$campaignName}_{$date}.csv", 'text/csv; charset=utf-8');
                break;
        }
    }
}

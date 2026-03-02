<?php

namespace App\Services;

use App\Core\Database;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Service d'export CSV, XLSX et PDF
 */
class ExportService
{
    /**
     * Exporte l'inventaire en CSV et retourne la chaîne
     */
    public function toCSV(int $campaignId, array $filters = []): string
    {
        $items   = $this->getInventoryItems($campaignId, $filters);
        $columns = $this->getDynamicColumns($campaignId);

        $output = fopen('php://temp', 'r+');

        // UTF-8 BOM pour Excel
        fputs($output, "\xEF\xBB\xBF");

        // En-têtes
        $headers = ['Code Immo', 'Local', 'Désignation', 'N° Série', 'Statut'];
        foreach ($columns as $col) {
            $headers[] = $col['label'];
        }
        $headers[] = 'Date création';
        fputcsv($output, $headers, ';');

        // Données
        foreach ($items as $item) {
            $attrs = json_decode($item['attributes'] ?? '{}', true) ?: [];
            $row   = [
                $item['code_immo'],
                $item['code_local'] ?? '',
                $item['designation'] ?? '',
                $item['serial_number'] ?? '',
                $item['status'],
            ];
            foreach ($columns as $col) {
                $val = $attrs[$col['column_key']] ?? '';
                if (is_array($val)) {
                    $val = implode(', ', $val);
                }
                $row[] = $val;
            }
            $row[] = $item['created_at'];
            fputcsv($output, $row, ';');
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }

    /**
     * Exporte l'inventaire en XLSX
     */
    public function toXLSX(int $campaignId, array $filters = []): string
    {
        $items    = $this->getInventoryItems($campaignId, $filters);
        $columns  = $this->getDynamicColumns($campaignId);
        $campaign = Database::fetchOne('SELECT name FROM campaigns WHERE id = ?', [$campaignId]);

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Inventaire');

        // Style entête
        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2c3e50']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];

        // En-têtes
        $headers = ['Code Immo', 'Local', 'Désignation', 'N° Série', 'Statut'];
        foreach ($columns as $col) {
            $headers[] = $col['label'];
        }
        $headers[] = 'Créé le';

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray($headerStyle);

        // Données
        $row = 2;
        foreach ($items as $item) {
            $attrs = json_decode($item['attributes'] ?? '{}', true) ?: [];
            $col   = 'A';

            $sheet->setCellValue($col++ . $row, $item['code_immo']);
            $sheet->setCellValue($col++ . $row, $item['code_local'] ?? '');
            $sheet->setCellValue($col++ . $row, $item['designation'] ?? '');
            $sheet->setCellValue($col++ . $row, $item['serial_number'] ?? '');
            $sheet->setCellValue($col++ . $row, $item['status']);

            foreach ($columns as $dc) {
                $val = $attrs[$dc['column_key']] ?? '';
                if (is_array($val)) $val = implode(', ', $val);
                $sheet->setCellValue($col++ . $row, $val);
            }

            $sheet->setCellValue($col . $row, $item['created_at']);
            $row++;
        }

        // Redimensionner automatiquement les colonnes
        foreach (range('A', $sheet->getHighestColumn()) as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        // Sauvegarder dans un fichier temporaire
        $tmpFile = tempnam(sys_get_temp_dir(), 'exfer_export_') . '.xlsx';
        $writer  = new Xlsx($spreadsheet);
        $writer->save($tmpFile);

        return $tmpFile;
    }

    /**
     * Génère un PV d'inventaire en PDF
     */
    public function toPDF(string $type, int $campaignId, array $filters = []): string
    {
        $items    = $this->getInventoryItems($campaignId, $filters);
        $campaign = Database::fetchOne(
            'SELECT c.*, s.name as service_name, o.name as org_name
             FROM campaigns c
             LEFT JOIN services s ON s.id = c.service_id
             LEFT JOIN organizations o ON o.id = c.organization_id
             WHERE c.id = ?',
            [$campaignId]
        );

        // Générer le HTML du PV
        $html = $this->generatePVHtml($type, $campaign, $items, $filters);

        // Utiliser Dompdf
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $tmpFile = tempnam(sys_get_temp_dir(), 'exfer_pv_') . '.pdf';
        file_put_contents($tmpFile, $dompdf->output());

        return $tmpFile;
    }

    /**
     * Exporte les anomalies en CSV
     */
    public function anomaliesToCSV(int $campaignId): string
    {
        $anomalies = Database::fetchAll(
            'SELECT a.*, i.code_immo, l.code_local, u.name as detected_by_name
             FROM anomalies a
             LEFT JOIN inventory_items i ON i.id = a.item_id
             LEFT JOIN locations l ON l.id = a.location_id
             LEFT JOIN users u ON u.id = a.detected_by
             WHERE a.campaign_id = ?
             ORDER BY a.created_at DESC',
            [$campaignId]
        );

        $output = fopen('php://temp', 'r+');
        fputs($output, "\xEF\xBB\xBF");
        fputcsv($output, ['ID', 'Type', 'Sévérité', 'Statut', 'Code Immo', 'Local', 'Description', 'Détecté par', 'Détecté le', 'Résolu le'], ';');

        foreach ($anomalies as $a) {
            fputcsv($output, [
                $a['id'],
                $a['type'],
                $a['severity'],
                $a['status'],
                $a['code_immo'] ?? '',
                $a['code_local'] ?? '',
                $a['description'],
                $a['detected_by_name'] ?? '',
                $a['detected_at'],
                $a['resolved_at'] ?? '',
            ], ';');
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);
        return $content;
    }

    // ─── Privé ────────────────────────────────────────────────────────────────

    private function getInventoryItems(int $campaignId, array $filters): array
    {
        $sql    = 'SELECT i.*, l.code_local, l.designation_local
                   FROM inventory_items i
                   LEFT JOIN locations l ON l.id = i.location_id
                   WHERE i.campaign_id = ?';
        $params = [$campaignId];

        if (!empty($filters['location_id'])) {
            $sql .= ' AND i.location_id = ?';
            $params[] = $filters['location_id'];
        }
        if (!empty($filters['site_id'])) {
            $sql .= ' AND i.site_id = ?';
            $params[] = $filters['site_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND i.status = ?';
            $params[] = $filters['status'];
        }

        $sql .= ' ORDER BY l.code_local, i.code_immo';
        return Database::fetchAll($sql, $params);
    }

    private function getDynamicColumns(int $campaignId): array
    {
        return Database::fetchAll(
            'SELECT * FROM dynamic_columns WHERE campaign_id = ? AND is_active = 1 ORDER BY position',
            [$campaignId]
        );
    }

    private function generatePVHtml(string $type, array $campaign, array $items, array $filters): string
    {
        $title  = $type === 'reform' ? 'PROCÈS-VERBAL DE RÉFORME' : "PROCÈS-VERBAL D'INVENTAIRE";
        $count  = count($items);
        $date   = date('d/m/Y');
        $orgName = htmlspecialchars($campaign['org_name'] ?? '');
        $campName= htmlspecialchars($campaign['name']);

        $rows = '';
        foreach ($items as $i => $item) {
            $rows .= '<tr>'
                . '<td>' . ($i + 1) . '</td>'
                . '<td>' . htmlspecialchars($item['code_immo']) . '</td>'
                . '<td>' . htmlspecialchars($item['code_local'] ?? '') . '</td>'
                . '<td>' . htmlspecialchars($item['designation'] ?? '') . '</td>'
                . '<td>' . htmlspecialchars($item['serial_number'] ?? '') . '</td>'
                . '</tr>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10pt; margin: 20mm; }
    h1 { text-align: center; font-size: 14pt; text-transform: uppercase; margin-bottom: 5mm; }
    .meta { margin-bottom: 10mm; }
    .meta p { margin: 2mm 0; }
    table { width: 100%; border-collapse: collapse; font-size: 9pt; }
    th { background: #2c3e50; color: white; padding: 3mm; text-align: left; }
    td { padding: 2mm 3mm; border-bottom: 1px solid #ddd; }
    tr:nth-child(even) td { background: #f8f9fa; }
    .footer { margin-top: 15mm; }
    .signatures { display: flex; justify-content: space-between; margin-top: 20mm; }
    .sig-block { width: 40%; border-top: 1px solid #000; padding-top: 5mm; text-align: center; }
</style>
</head>
<body>
<h1>{$title}</h1>
<div class="meta">
    <p><strong>Organisation :</strong> {$orgName}</p>
    <p><strong>Campagne :</strong> {$campName}</p>
    <p><strong>Date :</strong> {$date}</p>
    <p><strong>Nombre d'articles :</strong> {$count}</p>
</div>
<table>
<thead>
<tr><th>N°</th><th>Code Immo</th><th>Local</th><th>Désignation</th><th>N° Série</th></tr>
</thead>
<tbody>
{$rows}
</tbody>
</table>
<div class="footer">
    <p>Le présent procès-verbal a été établi contradictoirement entre les parties soussignées.</p>
    <div class="signatures">
        <div class="sig-block">Responsable inventaire<br><br><br>____________________________</div>
        <div class="sig-block">Responsable organisme<br><br><br>____________________________</div>
    </div>
</div>
</body>
</html>
HTML;
    }
}

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
use App\Services\AuditService;
use App\Services\ScopeService;

class ColumnController extends Controller
{
    public function index(Request $request, array $params): void
    {
        $this->requireRole('SUPERVISEUR');
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);

        $columns = Database::fetchAll(
            'SELECT * FROM dynamic_columns WHERE campaign_id = ? ORDER BY position',
            [$campaign['id']]
        );

        $this->render('columns/index', [
            'campaign'  => $campaign,
            'columns'   => $columns,
            'pageTitle' => 'Colonnes dynamiques',
        ]);
    }

    public function store(Request $request, array $params): void
    {
        $this->requireRole('SUPERVISEUR');
        CSRF::verify();
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);
        $orgId    = Auth::orgId();

        $errors = $request->validate([
            'label' => 'required|max:255',
            'type'  => 'required|in:text,number,date,select,multi_select,boolean,image,images,file',
        ]);

        if ($errors) {
            if ($request->isAjax()) $this->jsonError('Validation échouée.', 422, $errors);
            Session::flash('errors', $errors);
            Response::redirect('/campaigns/' . $campaign['id'] . '/columns');
        }

        // Générer une clé slug stable
        $label   = $request->post('label');
        $baseKey = self::slugify($label);
        $key     = $baseKey;
        $i       = 1;
        while (Database::fetchOne('SELECT id FROM dynamic_columns WHERE campaign_id = ? AND column_key = ?', [$campaign['id'], $key])) {
            $key = $baseKey . '_' . $i++;
        }

        // Position max
        $maxPos = (int)(Database::fetchScalar('SELECT MAX(position) FROM dynamic_columns WHERE campaign_id = ?', [$campaign['id']]) ?? 0);

        // Options pour select/multi_select
        $options    = null;
        $optionsRaw = $request->post('options');
        if ($optionsRaw && in_array($request->post('type'), ['select', 'multi_select'])) {
            $lines   = array_filter(array_map('trim', explode("\n", $optionsRaw)));
            $options = json_encode(array_values(array_map(fn($l) => ['value' => self::slugify($l), 'label' => $l], $lines)));
        }

        // Validation JSON
        $validation = [];
        if ($request->post('required')) $validation['required'] = true;
        if ($request->post('regex'))    $validation['regex']    = $request->post('regex');
        if ($request->post('min'))      $validation['min']      = $request->post('min');
        if ($request->post('max'))      $validation['max']      = $request->post('max');

        $id = Database::insert('dynamic_columns', [
            'organization_id' => $orgId,
            'campaign_id'     => $campaign['id'],
            'column_key'      => $key,
            'label'           => $label,
            'type'            => $request->post('type'),
            'options_json'    => $options,
            'validation_json' => !empty($validation) ? json_encode($validation) : null,
            'position'        => $maxPos + 1,
            'is_system'       => 0,
            'is_active'       => 1,
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        AuditService::log('CREATE_COLUMN', 'dynamic_column', (int)$id, null, ['key' => $key, 'label' => $label]);

        if ($request->isAjax()) {
            $col = Database::fetchOne('SELECT * FROM dynamic_columns WHERE id = ?', [$id]);
            $this->jsonSuccess(['column' => $col], 'Colonne ajoutée.');
        }

        Session::success('Colonne ajoutée.');
        Response::redirect('/campaigns/' . $campaign['id'] . '/columns');
    }

    public function update(Request $request, array $params): void
    {
        $this->requireRole('SUPERVISEUR');
        CSRF::verify();
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);

        $col = Database::fetchOne('SELECT * FROM dynamic_columns WHERE id = ? AND campaign_id = ?', [(int)$params['colid'], $campaign['id']]);
        if (!$col) Response::notFound();

        $oldType = $col['type'];
        $newType = $request->post('type') ?: $col['type'];

        // Si changement de type → analyser les valeurs existantes
        if ($oldType !== $newType) {
            $this->analyzeTypeConversion((int)$col['id'], $col['column_key'], $oldType, $newType, (int)$campaign['id']);
        }

        // Options
        $options    = $col['options_json'];
        $optionsRaw = $request->post('options');
        if ($optionsRaw && in_array($newType, ['select', 'multi_select'])) {
            $lines   = array_filter(array_map('trim', explode("\n", $optionsRaw)));
            $options = json_encode(array_values(array_map(fn($l) => ['value' => self::slugify($l), 'label' => $l], $lines)));
        }

        $validation = json_decode($col['validation_json'] ?? '{}', true) ?: [];
        if ($request->post('required') !== null) $validation['required'] = (bool)$request->post('required');
        if ($request->post('regex'))             $validation['regex']    = $request->post('regex');
        if ($request->post('min') !== null)      $validation['min']      = $request->post('min');
        if ($request->post('max') !== null)      $validation['max']      = $request->post('max');

        Database::update('dynamic_columns', [
            'label'           => $request->post('label') ?: $col['label'],
            'type'            => $newType,
            'options_json'    => $options,
            'validation_json' => !empty($validation) ? json_encode($validation) : null,
            'position'        => (int)($request->post('position') ?? $col['position']),
            'updated_at'      => date('Y-m-d H:i:s'),
        ], ['id' => $col['id']]);

        AuditService::log('UPDATE_COLUMN', 'dynamic_column', $col['id'], ['type' => $oldType], ['type' => $newType]);

        if ($request->isAjax()) {
            $updated = Database::fetchOne('SELECT * FROM dynamic_columns WHERE id = ?', [$col['id']]);
            $this->jsonSuccess(['column' => $updated], 'Colonne mise à jour.');
        }

        Session::success('Colonne mise à jour.');
        Response::redirect('/campaigns/' . $campaign['id'] . '/columns');
    }

    public function delete(Request $request, array $params): void
    {
        $this->requireRole('SUPERVISEUR');
        CSRF::verify();
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);

        $col = Database::fetchOne('SELECT * FROM dynamic_columns WHERE id = ? AND campaign_id = ?', [(int)$params['colid'], $campaign['id']]);
        if (!$col) Response::notFound();

        if ($col['is_system']) {
            if ($request->isAjax()) $this->jsonError('Les colonnes système ne peuvent pas être supprimées.', 403);
            Session::error('Les colonnes système ne peuvent pas être supprimées.');
            Response::redirect('/campaigns/' . $campaign['id'] . '/columns');
        }

        // Soft delete : désactiver plutôt que supprimer (les données JSON restent)
        Database::update('dynamic_columns', ['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')], ['id' => $col['id']]);
        AuditService::log('DELETE_COLUMN', 'dynamic_column', $col['id']);

        if ($request->isAjax()) $this->jsonSuccess(null, 'Colonne supprimée.');
        Session::success('Colonne supprimée.');
        Response::redirect('/campaigns/' . $campaign['id'] . '/columns');
    }

    public function reorder(Request $request, array $params): void
    {
        $this->requireRole('SUPERVISEUR');
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);
        $data     = $request->json();
        $order    = $data['order'] ?? [];

        foreach ($order as $pos => $colId) {
            Database::update('dynamic_columns', ['position' => (int)$pos + 1, 'updated_at' => date('Y-m-d H:i:s')], ['id' => (int)$colId, 'campaign_id' => $campaign['id']]);
        }

        $this->jsonSuccess(null, 'Ordre mis à jour.');
    }

    // ─── Privé ────────────────────────────────────────────────────────────────

    private function analyzeTypeConversion(int $colId, string $colKey, string $oldType, string $newType, int $campaignId): void
    {
        $anomalySvc = new AnomalyService();

        $items = Database::fetchAll(
            'SELECT id, attributes FROM inventory_items WHERE campaign_id = ? AND attributes IS NOT NULL AND JSON_EXTRACT(attributes, ?) IS NOT NULL',
            [$campaignId, '$.' . $colKey]
        );

        foreach ($items as $item) {
            $attrs = json_decode($item['attributes'], true) ?: [];
            $val   = $attrs[$colKey] ?? null;

            if ($val === null) continue;

            $canConvert = $this->canConvert($val, $oldType, $newType);

            if (!$canConvert) {
                $anomalySvc->create(
                    'TYPE_CONVERSION_ERROR',
                    'WARNING',
                    $campaignId,
                    null,
                    $item['id'],
                    "Valeur '{$val}' (champ {$colKey}) incompatible avec le nouveau type {$newType}",
                    ['column_key' => $colKey, 'old_type' => $oldType, 'new_type' => $newType, 'value' => $val]
                );
            }
        }
    }

    private function canConvert(mixed $val, string $oldType, string $newType): bool
    {
        $str = (string)$val;
        return match($newType) {
            'number'  => is_numeric($str),
            'boolean' => in_array(strtolower($str), ['true', 'false', '1', '0', 'oui', 'non', 'yes', 'no']),
            'date'    => strtotime($str) !== false,
            default   => true,
        };
    }

    private static function slugify(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '_', $text);
        return trim($text, '_') ?: 'col';
    }
}

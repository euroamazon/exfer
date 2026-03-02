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
use App\Services\CodeValidationService;
use App\Services\LabelRollService;
use App\Services\ScopeService;
use App\Services\VisionService;

/**
 * Contrôleur API JSON pour la gestion des articles d'inventaire (vue tableur)
 */
class InventoryController extends Controller
{
    /**
     * GET /api/campaigns/{cid}/locations/{lid}/items
     * Retourne tous les articles d'un local
     */
    public function getItems(Request $request, array $params): void
    {
        $this->requireAuth();
        $location = ScopeService::requireLocationAccess((int)$params['lid']);
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);

        $items = Database::fetchAll(
            'SELECT i.*, u1.name as created_by_name, u2.name as updated_by_name
             FROM inventory_items i
             LEFT JOIN users u1 ON u1.id = i.created_by
             LEFT JOIN users u2 ON u2.id = i.updated_by
             WHERE i.location_id = ?
             ORDER BY i.code_immo',
            [$location['id']]
        );

        // Décoder les JSON
        foreach ($items as &$item) {
            $item['attributes']  = json_decode($item['attributes'] ?? '{}', true) ?: [];
            $item['photos_json'] = json_decode($item['photos_json'] ?? '[]', true) ?: [];
        }

        $columns = Database::fetchAll(
            'SELECT * FROM dynamic_columns WHERE campaign_id = ? AND is_active = 1 ORDER BY position',
            [$campaign['id']]
        );

        $this->jsonSuccess(['items' => $items, 'columns' => $columns]);
    }

    /**
     * POST /api/campaigns/{cid}/locations/{lid}/items
     * Crée un nouvel article
     */
    public function createItem(Request $request, array $params): void
    {
        $this->requireAuth();
        $location = ScopeService::requireLocationAccess((int)$params['lid']);
        $campaign = ScopeService::requireCampaignAccess((int)$params['cid']);
        $orgId    = Auth::orgId();

        if ($campaign['status'] === 'CLOSED') {
            $this->jsonError('Campagne clôturée.', 403);
        }

        $data    = $request->json();
        $config  = json_decode($campaign['config'] ?? '{}', true) ?: [];
        $anomalySvc = new AnomalyService();

        // Gestion du code
        $codeImmo = $data['code_immo'] ?? null;
        $rollId   = null;

        if ($codeImmo) {
            // Saisie manuelle
            $errors = CodeValidationService::validateFull((string)$codeImmo, $config, $campaign['id'], $orgId);

            if ($errors && !empty($config['manual_entry_enabled'])) {
                if ($config['manual_entry_mode'] === 'STRICT') {
                    $this->jsonError('Code invalide: ' . implode(', ', $errors), 422);
                }
                // Mode FLEX : accepter mais créer une anomalie
                // Vérifier séquence
                $expected = CodeValidationService::suggestNextCode($campaign['id'], $orgId, $config);
                if ($codeImmo !== $expected) {
                    $anomalySvc->create(
                        'CODE_HORS_SEQUENCE', 'WARNING',
                        $campaign['id'], $location['id'], null,
                        "Code {$codeImmo} hors séquence (attendu: {$expected})",
                        ['code' => $codeImmo, 'expected' => $expected]
                    );
                }
            } elseif ($errors) {
                $this->jsonError('Code invalide: ' . implode(', ', $errors), 422);
            }
        } else {
            // Attribution automatique via rouleau ou séquence
            if (!empty($config['label_roll_enabled'])) {
                $roll = LabelRollService::getOpenRoll(Auth::id(), $campaign['id']);
                if (!$roll) {
                    $this->jsonError("Aucun rouleau ouvert. Veuillez créer un rouleau d'étiquettes.", 422);
                }
                try {
                    $codeImmo = LabelRollService::consumeNextCode($roll['id']);
                    $rollId   = $roll['id'];
                } catch (\RuntimeException $e) {
                    $this->jsonError($e->getMessage(), 422);
                }
            } else {
                $codeImmo = CodeValidationService::suggestNextCode($campaign['id'], $orgId, $config);
            }
        }

        // Valider les colonnes dynamiques
        $columns    = Database::fetchAll('SELECT * FROM dynamic_columns WHERE campaign_id = ? AND is_active = 1', [$campaign['id']]);
        $attributes = [];
        $colErrors  = [];

        foreach ($columns as $col) {
            $val = $data['attributes'][$col['column_key']] ?? null;
            $validation = json_decode($col['validation_json'] ?? '{}', true) ?: [];

            if (!empty($validation['required']) && ($val === null || $val === '')) {
                $colErrors[] = "Le champ '{$col['label']}' est obligatoire.";
            }

            $attributes[$col['column_key']] = $val;
        }

        if ($colErrors) {
            $this->jsonError(implode(' ', $colErrors), 422);
        }

        // Créer l'article
        $id = Database::insert('inventory_items', [
            'organization_id' => $orgId,
            'campaign_id'     => $campaign['id'],
            'location_id'     => $location['id'],
            'site_id'         => $location['site_id'],
            'code_immo'       => $codeImmo,
            'designation'     => $data['designation'] ?? null,
            'serial_number'   => $data['serial_number'] ?? null,
            'label_roll_id'   => $rollId,
            'attributes'      => json_encode($attributes, JSON_UNESCAPED_UNICODE),
            'photos_json'     => '[]',
            'status'          => 'DRAFT',
            'notes'           => $data['notes'] ?? null,
            'created_by'      => Auth::id(),
            'updated_by'      => Auth::id(),
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        // Mettre à jour items_count du local
        Database::query('UPDATE locations SET items_count = items_count + 1, updated_at = NOW() WHERE id = ?', [$location['id']]);

        // Remettre le local en IN_PROGRESS si VALIDATED (modification après validation)
        if ($location['status'] === 'VALIDATED') {
            Database::update('locations', ['status' => 'NEEDS_REVIEW', 'updated_at' => date('Y-m-d H:i:s')], ['id' => $location['id']]);
        }

        AuditService::log('CREATE_ITEM', 'inventory_item', (int)$id, null, ['code_immo' => $codeImmo]);

        $item = Database::fetchOne('SELECT * FROM inventory_items WHERE id = ?', [$id]);
        $item['attributes']  = json_decode($item['attributes'] ?? '{}', true) ?: [];
        $item['photos_json'] = json_decode($item['photos_json'] ?? '[]', true) ?: [];

        // Prochain code suggéré
        $nextSuggestion = CodeValidationService::suggestNextCode($campaign['id'], $orgId, $config);

        $this->jsonSuccess(['item' => $item, 'next_code' => $nextSuggestion], 'Article créé.');
    }

    /**
     * PUT /api/items/{id}
     * Met à jour un article
     */
    public function updateItem(Request $request, array $params): void
    {
        $this->requireAuth();
        $orgId = Auth::orgId();

        $item = Database::fetchOne('SELECT * FROM inventory_items WHERE id = ? AND organization_id = ?', [(int)$params['id'], $orgId]);
        if (!$item) {
            $this->jsonError('Article introuvable.', 404);
        }

        $location = ScopeService::requireLocationAccess($item['location_id']);
        $campaign = ScopeService::requireCampaignAccess($item['campaign_id']);

        if ($campaign['status'] === 'CLOSED') {
            $this->jsonError('Campagne clôturée.', 403);
        }

        $data    = $request->json();
        $config  = json_decode($campaign['config'] ?? '{}', true) ?: [];
        $old     = $item;

        $updates = ['updated_at' => date('Y-m-d H:i:s'), 'updated_by' => Auth::id()];

        if (isset($data['designation'])) {
            $updates['designation'] = $data['designation'];
        }
        if (isset($data['serial_number'])) {
            $updates['serial_number'] = $data['serial_number'];
        }
        if (isset($data['notes'])) {
            $updates['notes'] = $data['notes'];
        }

        // Mise à jour code_immo
        if (isset($data['code_immo']) && $data['code_immo'] !== $item['code_immo']) {
            $errors = CodeValidationService::validateFull((string)$data['code_immo'], $config, $item['campaign_id'], $orgId, $item['id']);
            if ($errors) {
                $this->jsonError('Code invalide: ' . implode(', ', $errors), 422);
            }
            $updates['code_immo'] = $data['code_immo'];
        }

        // Attributs dynamiques
        if (isset($data['attributes'])) {
            $existingAttrs = json_decode($item['attributes'] ?? '{}', true) ?: [];
            $newAttrs      = array_merge($existingAttrs, $data['attributes']);
            $updates['attributes'] = json_encode($newAttrs, JSON_UNESCAPED_UNICODE);
        }

        Database::update('inventory_items', $updates, ['id' => $item['id']]);

        AuditService::log('UPDATE_ITEM', 'inventory_item', $item['id'],
            ['designation' => $old['designation'], 'attributes' => $old['attributes']],
            ['designation' => $updates['designation'] ?? $old['designation']]
        );

        // Remettre en NEEDS_REVIEW si local était VALIDATED
        if ($location['status'] === 'VALIDATED') {
            Database::update('locations', ['status' => 'NEEDS_REVIEW', 'updated_at' => date('Y-m-d H:i:s')], ['id' => $location['id']]);
        }

        $updated = Database::fetchOne('SELECT * FROM inventory_items WHERE id = ?', [$item['id']]);
        $updated['attributes']  = json_decode($updated['attributes'] ?? '{}', true) ?: [];
        $updated['photos_json'] = json_decode($updated['photos_json'] ?? '[]', true) ?: [];

        $this->jsonSuccess(['item' => $updated], 'Article mis à jour.');
    }

    /**
     * DELETE /api/items/{id}
     */
    public function deleteItem(Request $request, array $params): void
    {
        $this->requireAuth();
        $orgId = Auth::orgId();

        $item = Database::fetchOne('SELECT * FROM inventory_items WHERE id = ? AND organization_id = ?', [(int)$params['id'], $orgId]);
        if (!$item) {
            $this->jsonError('Article introuvable.', 404);
        }

        $campaign = ScopeService::requireCampaignAccess($item['campaign_id']);
        if ($campaign['status'] === 'CLOSED') {
            $this->jsonError('Campagne clôturée.', 403);
        }

        // Supprimer les photos
        $photos = json_decode($item['photos_json'] ?? '[]', true) ?: [];
        foreach ($photos as $photo) {
            if (!empty($photo['path']) && file_exists(STORAGE_PATH . '/uploads/' . $photo['path'])) {
                unlink(STORAGE_PATH . '/uploads/' . $photo['path']);
            }
        }

        Database::query('DELETE FROM inventory_items WHERE id = ?', [$item['id']]);
        Database::query('UPDATE locations SET items_count = GREATEST(0, items_count - 1), updated_at = NOW() WHERE id = ?', [$item['location_id']]);

        AuditService::log('DELETE_ITEM', 'inventory_item', $item['id'], ['code_immo' => $item['code_immo']], null);
        $this->jsonSuccess(null, 'Article supprimé.');
    }

    /**
     * POST /api/items/{id}/duplicate
     * Duplique un article
     */
    public function duplicateItem(Request $request, array $params): void
    {
        $this->requireAuth();
        $orgId = Auth::orgId();

        $original = Database::fetchOne('SELECT * FROM inventory_items WHERE id = ? AND organization_id = ?', [(int)$params['id'], $orgId]);
        if (!$original) {
            $this->jsonError('Article introuvable.', 404);
        }

        $campaign = ScopeService::requireCampaignAccess($original['campaign_id']);
        if ($campaign['status'] === 'CLOSED') {
            $this->jsonError('Campagne clôturée.', 403);
        }

        $data   = $request->json();
        $count  = max(1, min(50, (int)($data['count'] ?? 1)));
        $config = json_decode($campaign['config'] ?? '{}', true) ?: [];

        $createdItems = [];

        Database::beginTransaction();
        try {
            for ($i = 1; $i <= $count; $i++) {
                // Obtenir le prochain code
                $roll = null;
                if (!empty($config['label_roll_enabled'])) {
                    $roll = LabelRollService::getOpenRoll(Auth::id(), $campaign['id']);
                }

                if ($roll) {
                    $newCode = LabelRollService::consumeNextCode($roll['id']);
                    $rollId  = $roll['id'];
                } else {
                    $newCode = CodeValidationService::suggestNextCode($campaign['id'], $orgId, $config);
                    $rollId  = null;
                }

                // Copier les attributs
                $attrs = json_decode($original['attributes'] ?? '{}', true) ?: [];

                // Gérer les photos
                $photos = !empty($config['duplicate_copy_photos'])
                    ? json_decode($original['photos_json'] ?? '[]', true) ?: []
                    : [];

                // Reset des champs configurés
                $resetFields = $config['duplicate_reset_fields'] ?? [];
                foreach ((array)$resetFields as $field) {
                    unset($attrs[$field]);
                }

                $newId = Database::insert('inventory_items', [
                    'organization_id' => $orgId,
                    'campaign_id'     => $original['campaign_id'],
                    'location_id'     => $original['location_id'],
                    'site_id'         => $original['site_id'],
                    'code_immo'       => $newCode,
                    'designation'     => $original['designation'],
                    'serial_number'   => null, // Reset SN sur duplication
                    'label_roll_id'   => $rollId,
                    'attributes'      => json_encode($attrs, JSON_UNESCAPED_UNICODE),
                    'photos_json'     => json_encode($photos),
                    'status'          => 'DRAFT',
                    'is_duplicate_of' => $original['id'],
                    'duplicate_index' => $i,
                    'notes'           => null,
                    'created_by'      => Auth::id(),
                    'updated_by'      => Auth::id(),
                    'created_at'      => date('Y-m-d H:i:s'),
                    'updated_at'      => date('Y-m-d H:i:s'),
                ]);

                $createdItems[] = $newId;
                AuditService::log('DUPLICATE_ITEM', 'inventory_item', (int)$newId, null, [
                    'original_id' => $original['id'],
                    'code_immo'   => $newCode,
                ]);
            }

            Database::query('UPDATE locations SET items_count = items_count + ?, updated_at = NOW() WHERE id = ?', [$count, $original['location_id']]);
            Database::commit();

        } catch (\Throwable $e) {
            Database::rollback();
            $this->jsonError($e->getMessage(), 500);
        }

        // Retourner les nouveaux items
        $newItems = Database::fetchAll(
            'SELECT * FROM inventory_items WHERE id IN (' . implode(',', array_fill(0, count($createdItems), '?')) . ')',
            $createdItems
        );
        foreach ($newItems as &$item) {
            $item['attributes']  = json_decode($item['attributes'] ?? '{}', true) ?: [];
            $item['photos_json'] = json_decode($item['photos_json'] ?? '[]', true) ?: [];
        }

        $nextCode = CodeValidationService::suggestNextCode($campaign['id'], $orgId, $config);
        $this->jsonSuccess(['items' => $newItems, 'next_code' => $nextCode], "{$count} article(s) dupliqué(s).");
    }

    /**
     * POST /api/items/{id}/photos
     * Upload une photo pour un article
     */
    public function uploadPhoto(Request $request, array $params): void
    {
        $this->requireAuth();
        $orgId = Auth::orgId();

        $item = Database::fetchOne('SELECT * FROM inventory_items WHERE id = ? AND organization_id = ?', [(int)$params['id'], $orgId]);
        if (!$item) {
            $this->jsonError('Article introuvable.', 404);
        }

        $campaign = ScopeService::requireCampaignAccess($item['campaign_id']);
        if ($campaign['status'] === 'CLOSED') {
            $this->jsonError('Campagne clôturée.', 403);
        }

        $file = $request->file('photo');
        if (!$file) {
            $this->jsonError('Aucun fichier reçu.', 400);
        }

        // Valider le fichier
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->jsonError('Erreur lors de l\'upload.', 400);
        }

        // Vérifier le type MIME réel (pas seulement l'extension)
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, $allowedMimes)) {
            $this->jsonError('Type de fichier non autorisé. Utilisez JPEG, PNG ou WebP.', 400);
        }

        // Limiter la taille (10 Mo)
        if ($file['size'] > 10 * 1024 * 1024) {
            $this->jsonError('Le fichier est trop volumineux (max 10 Mo).', 400);
        }

        // Générer un nom unique
        $ext      = match($mimeType) { 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', default => 'jpg' };
        $dir      = 'photos/' . date('Y/m');
        $fullDir  = STORAGE_PATH . '/uploads/' . $dir;

        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }

        $filename = uniqid('photo_') . '_' . time() . '.' . $ext;
        $destPath = $fullDir . '/' . $filename;
        $relPath  = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            $this->jsonError('Impossible de sauvegarder le fichier.', 500);
        }

        // Vision IA si activée
        $visionResult = null;
        $config       = json_decode($campaign['config'] ?? '{}', true) ?: [];
        $anomalyId    = null;

        if (!empty($config['vision_enabled'])) {
            $visionSvc = new VisionService();
            $anomalySvc = new AnomalyService();

            try {
                $visionResult = $visionSvc->classify(
                    $destPath,
                    $item['designation'] ?? '',
                    (float)($config['vision_threshold'] ?? 0.75)
                );

                $anomalyId = $visionSvc->handleMismatch(
                    $item['id'],
                    $item['campaign_id'],
                    $item['location_id'],
                    $visionResult,
                    $item['designation'] ?? '',
                    (float)($config['vision_threshold'] ?? 0.75),
                    $config['vision_mode'] ?? 'WARNING',
                    $anomalySvc
                );
            } catch (\Throwable $e) {
                // La vision ne bloque pas l'upload
                error_log('[Vision] Erreur: ' . $e->getMessage());
            }
        }

        // Ajouter la photo
        $photos = json_decode($item['photos_json'] ?? '[]', true) ?: [];
        $photoEntry = [
            'id'               => uniqid(),
            'path'             => $relPath,
            'original_name'    => $file['name'],
            'uploaded_at'      => date('Y-m-d H:i:s'),
            'vision_label'     => $visionResult['predicted_label'] ?? null,
            'vision_confidence'=> $visionResult['confidence'] ?? null,
        ];
        $photos[] = $photoEntry;

        Database::update('inventory_items', [
            'photos_json' => json_encode($photos),
            'updated_at'  => date('Y-m-d H:i:s'),
            'updated_by'  => Auth::id(),
        ], ['id' => $item['id']]);

        AuditService::log('UPLOAD_PHOTO', 'inventory_item', $item['id'], null, ['photo' => $relPath]);

        $this->jsonSuccess([
            'photo'        => $photoEntry,
            'vision'       => $visionResult,
            'anomaly_id'   => $anomalyId,
            'mismatch'     => $anomalyId !== null,
        ], 'Photo ajoutée.');
    }

    /**
     * DELETE /api/items/{id}/photos/{photo_id}
     */
    public function deletePhoto(Request $request, array $params): void
    {
        $this->requireAuth();
        $orgId = Auth::orgId();

        $item = Database::fetchOne('SELECT * FROM inventory_items WHERE id = ? AND organization_id = ?', [(int)$params['id'], $orgId]);
        if (!$item) {
            $this->jsonError('Article introuvable.', 404);
        }

        $photos   = json_decode($item['photos_json'] ?? '[]', true) ?: [];
        $photoId  = $params['photo_id'];
        $filtered = array_filter($photos, fn($p) => $p['id'] !== $photoId);

        if (count($filtered) === count($photos)) {
            $this->jsonError('Photo introuvable.', 404);
        }

        // Supprimer le fichier
        $photo = current(array_filter($photos, fn($p) => $p['id'] === $photoId));
        if ($photo && !empty($photo['path'])) {
            $fullPath = STORAGE_PATH . '/uploads/' . $photo['path'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        Database::update('inventory_items', [
            'photos_json' => json_encode(array_values($filtered)),
            'updated_at'  => date('Y-m-d H:i:s'),
        ], ['id' => $item['id']]);

        $this->jsonSuccess(null, 'Photo supprimée.');
    }
}

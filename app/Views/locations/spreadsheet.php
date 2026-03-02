<div class="container-fluid py-3">
    <!-- En-tête local -->
    <div class="d-flex align-items-center gap-3 mb-3">
        <a href="/campaigns/<?= $campaign['id'] ?>/locations" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div class="flex-grow-1">
            <h4 class="mb-0">
                <span class="badge bg-primary me-2">LOCAL</span>
                <?= \App\Core\View::e($location['code_local']) ?>
                <small class="text-muted fw-normal ms-2"><?= \App\Core\View::e($location['designation_local'] ?? '') ?></small>
            </h4>
            <small class="text-muted">
                Campagne : <?= \App\Core\View::e($campaign['name']) ?> —
                <?= \App\Core\View::statusBadge($location['status']) ?>
            </small>
        </div>
        <div class="d-flex gap-2">
            <?php if (!empty($openRoll)): ?>
            <span class="badge bg-success fs-6">
                <i class="bi bi-upc me-1"></i>Rouleau: <?= \App\Core\View::e($openRoll['next_code']) ?>
            </span>
            <?php endif; ?>
            <?php if (!empty($suggestedCode)): ?>
            <span class="badge bg-light text-dark border" title="Prochain code suggéré">
                Prochain: <strong><?= \App\Core\View::e($suggestedCode) ?></strong>
            </span>
            <?php endif; ?>
            <!-- Validation -->
            <?php if ($campaign['status'] === 'ACTIVE'): ?>
            <button class="btn btn-success btn-sm" id="btn-validate-location"
                    data-url="/campaigns/<?= $campaign['id'] ?>/locations/<?= $location['id'] ?>/validate">
                <i class="bi bi-check-circle me-1"></i>Valider le local
            </button>
            <?php endif; ?>
            <!-- Export local -->
            <a href="/campaigns/<?= $campaign['id'] ?>/export?format=csv&location_id=<?= $location['id'] ?>"
               class="btn btn-sm btn-outline-secondary" title="Exporter CSV">
                <i class="bi bi-download"></i>
            </a>
        </div>
    </div>

    <!-- Anomalies du local -->
    <?php if (!empty($anomalies)): ?>
    <div class="alert alert-warning py-2 mb-3">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong><?= count($anomalies) ?> anomalie(s)</strong> sur ce local —
        <a href="/campaigns/<?= $campaign['id'] ?>/anomalies" class="alert-link">Voir les anomalies</a>
    </div>
    <?php endif; ?>

    <!-- Barre d'outils tableur -->
    <div class="d-flex gap-2 mb-2">
        <button class="btn btn-primary btn-sm" id="btn-add-row">
            <i class="bi bi-plus-circle me-1"></i>Ajouter un article
        </button>
        <button class="btn btn-outline-secondary btn-sm" id="btn-add-manual">
            <i class="bi bi-pencil me-1"></i>Saisie manuelle du code
        </button>
        <div class="ms-auto d-flex gap-2">
            <input type="text" class="form-control form-control-sm" id="search-items" placeholder="Filtrer..." style="width:200px">
            <select class="form-select form-select-sm" id="filter-status" style="width:150px">
                <option value="">Tous les statuts</option>
                <option value="DRAFT">Brouillon</option>
                <option value="VALIDATED">Validé</option>
            </select>
        </div>
    </div>

    <!-- Tableau / Spreadsheet -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive" style="max-height: calc(100vh - 280px); overflow-y: auto;">
            <table class="table table-hover table-sm mb-0 spreadsheet-table" id="inventory-table">
                <thead class="table-dark sticky-top">
                    <tr>
                        <th class="sticky-col" style="min-width:130px">Code Immo</th>
                        <th style="min-width:120px">Local</th>
                        <th style="min-width:250px">Désignation</th>
                        <th style="min-width:150px">N° Série</th>
                        <?php foreach ($columns as $col): ?>
                        <th style="min-width:130px" title="<?= \App\Core\View::e($col['column_key']) ?>">
                            <?= \App\Core\View::e($col['label']) ?>
                        </th>
                        <?php endforeach; ?>
                        <th style="min-width:80px">Photos</th>
                        <th style="min-width:100px">Statut</th>
                        <th style="min-width:100px">Actions</th>
                    </tr>
                </thead>
                <tbody id="inventory-tbody">
                    <?php foreach ($items as $item):
                        $attrs = json_decode($item['attributes'] ?? '{}', true) ?: [];
                        $photos = json_decode($item['photos_json'] ?? '[]', true) ?: [];
                    ?>
                    <tr data-item-id="<?= $item['id'] ?>" class="item-row">
                        <td class="sticky-col fw-mono">
                            <span class="editable" data-field="code_immo" data-type="text">
                                <?= \App\Core\View::e($item['code_immo']) ?>
                            </span>
                        </td>
                        <td class="text-muted small"><?= \App\Core\View::e($location['code_local']) ?></td>
                        <td>
                            <span class="editable" data-field="designation" data-type="text">
                                <?= \App\Core\View::e($item['designation'] ?? '') ?>
                            </span>
                        </td>
                        <td>
                            <span class="editable" data-field="serial_number" data-type="text">
                                <?= \App\Core\View::e($item['serial_number'] ?? '') ?>
                            </span>
                        </td>
                        <?php foreach ($columns as $col):
                            $val = $attrs[$col['column_key']] ?? null;
                            $displayVal = is_array($val) ? implode(', ', $val) : ($val ?? '');
                        ?>
                        <td>
                            <span class="editable"
                                  data-field="attr_<?= $col['column_key'] ?>"
                                  data-col-key="<?= $col['column_key'] ?>"
                                  data-type="<?= $col['type'] ?>"
                                  data-options='<?= htmlspecialchars($col['options_json'] ?? '[]') ?>'>
                                <?= \App\Core\View::e($displayVal) ?>
                            </span>
                        </td>
                        <?php endforeach; ?>
                        <td>
                            <button class="btn btn-sm btn-outline-secondary btn-photo"
                                    data-item-id="<?= $item['id'] ?>"
                                    title="<?= count($photos) ?> photo(s)">
                                <i class="bi bi-camera"></i>
                                <?php if (count($photos) > 0): ?>
                                <span class="badge bg-primary"><?= count($photos) ?></span>
                                <?php endif; ?>
                            </button>
                        </td>
                        <td><?= \App\Core\View::statusBadge($item['status']) ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary btn-duplicate" data-item-id="<?= $item['id'] ?>" title="Dupliquer">
                                    <i class="bi bi-copy"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-delete" data-item-id="<?= $item['id'] ?>" title="Supprimer">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
            <span class="text-muted small" id="items-count"><?= count($items) ?> article(s)</span>
            <div id="autosave-indicator" class="text-muted small" style="display:none;">
                <span class="spinner-border spinner-border-sm me-1"></span>Enregistrement...
            </div>
        </div>
    </div>
</div>

<!-- Modal : Saisie manuelle du code -->
<div class="modal fade" id="manualCodeModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Saisie manuelle du code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Code attendu :</label>
                    <input type="text" class="form-control bg-light" id="suggested-code-display"
                           value="<?= \App\Core\View::e($suggestedCode ?? '') ?>" readonly>
                </div>
                <div class="mb-2">
                    <label class="form-label">Votre code :</label>
                    <input type="text" class="form-control" id="manual-code-input" placeholder="Ex: 000123">
                </div>
                <div id="manual-code-warning" class="alert alert-warning small py-2" style="display:none;">
                    <i class="bi bi-exclamation-triangle me-1"></i>Ce code diffère du code attendu. Une anomalie sera créée.
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button class="btn btn-primary" id="btn-confirm-manual-code">Confirmer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal : Photos -->
<div class="modal fade" id="photoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-camera me-2"></i>Photos de l'article</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="photo-modal-body">
                <!-- Chargé dynamiquement -->
            </div>
            <div class="modal-footer">
                <label class="btn btn-primary" id="btn-upload-photo">
                    <i class="bi bi-upload me-1"></i>Ajouter une photo
                    <input type="file" id="photo-input" accept="image/*" style="display:none">
                </label>
            </div>
        </div>
    </div>
</div>

<!-- Modal : Duplication -->
<div class="modal fade" id="duplicateModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-copy me-2"></i>Dupliquer l'article</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Nombre de copies :</label>
                <input type="number" class="form-control" id="dup-count" value="1" min="1" max="50">
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button class="btn btn-primary" id="btn-confirm-duplicate">Dupliquer</button>
            </div>
        </div>
    </div>
</div>

<script>
const SPREADSHEET_CONFIG = {
    campaignId: <?= $campaign['id'] ?>,
    locationId: <?= $location['id'] ?>,
    suggestedCode: <?= json_encode($suggestedCode) ?>,
    manualEntryEnabled: <?= !empty($config['manual_entry_enabled']) ? 'true' : 'false' ?>,
    manualEntryMode: <?= json_encode($config['manual_entry_mode'] ?? 'FLEX_WITH_ANOMALY') ?>,
    labelRollEnabled: <?= !empty($config['label_roll_enabled']) ? 'true' : 'false' ?>,
    visionEnabled: <?= !empty($config['vision_enabled']) ? 'true' : 'false' ?>,
    csrfToken: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
    columns: <?= json_encode(array_map(fn($c) => [
        'key' => $c['column_key'], 'label' => $c['label'], 'type' => $c['type'],
        'options' => json_decode($c['options_json'] ?? '[]', true)
    ], $columns)) ?>
};
</script>
<script src="/assets/js/spreadsheet.js"></script>

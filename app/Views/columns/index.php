<div class="container-fluid py-4">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="/campaigns/<?= $campaign['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
                <h2 class="mb-0">Colonnes dynamiques — <?= \App\Core\View::e($campaign['name']) ?></h2>
            </div>
            <p class="text-muted mb-0 small">Définissez les champs personnalisés pour la saisie des articles.</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- Formulaire d'ajout -->
        <?php if (\App\Core\Auth::isSuperviseur()): ?>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white pt-3">
                    <h6 class="card-title mb-0"><i class="bi bi-plus-circle me-2"></i>Nouvelle colonne</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="/campaigns/<?= $campaign['id'] ?>/columns">
                        <?= \App\Core\CSRF::field() ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Libellé <span class="text-danger">*</span></label>
                            <input type="text" name="label" class="form-control" placeholder="Ex : Valeur d'acquisition" required maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" id="colTypeSelect" required>
                                <option value="text">Texte</option>
                                <option value="number">Nombre</option>
                                <option value="date">Date</option>
                                <option value="select">Liste déroulante</option>
                                <option value="multi_select">Sélection multiple</option>
                                <option value="boolean">Oui / Non</option>
                                <option value="image">Image (1)</option>
                                <option value="images">Images (multiple)</option>
                                <option value="file">Fichier</option>
                            </select>
                        </div>
                        <div id="optionsBlock" class="mb-3 d-none">
                            <label class="form-label fw-semibold">Options (une par ligne)</label>
                            <textarea name="options" class="form-control" rows="4" placeholder="Bon état&#10;État moyen&#10;Mauvais état"></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="required" id="colRequired" value="1">
                                <label class="form-check-label" for="colRequired">Champ obligatoire</label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-plus me-1"></i>Ajouter
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Liste des colonnes -->
        <div class="col-md-<?= \App\Core\Auth::isSuperviseur() ? '8' : '12' ?>">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white pt-3 d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0"><i class="bi bi-layout-three-columns me-2"></i>Colonnes (<?= count($columns) ?>)</h6>
                    <?php if (\App\Core\Auth::isSuperviseur() && count($columns) > 1): ?>
                    <button class="btn btn-sm btn-outline-secondary" id="saveOrderBtn" style="display:none!important;">
                        <i class="bi bi-arrow-down-up me-1"></i>Enregistrer l'ordre
                    </button>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <?php if (\App\Core\Auth::isSuperviseur()): ?><th style="width:30px;"></th><?php endif; ?>
                                <th>Libellé</th>
                                <th>Clé</th>
                                <th>Type</th>
                                <th>Obligatoire</th>
                                <th>Statut</th>
                                <?php if (\App\Core\Auth::isSuperviseur()): ?><th></th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="columnsList">
                            <?php foreach ($columns as $col): ?>
                            <tr id="col-row-<?= $col['id'] ?>" data-id="<?= $col['id'] ?>">
                                <?php if (\App\Core\Auth::isSuperviseur()): ?>
                                <td><i class="bi bi-grip-vertical text-muted drag-handle" style="cursor:grab;"></i></td>
                                <?php endif; ?>
                                <td class="fw-semibold"><?= \App\Core\View::e($col['label']) ?></td>
                                <td><code class="text-muted"><?= \App\Core\View::e($col['column_key']) ?></code></td>
                                <td>
                                    <?php
                                    $typeLabels = [
                                        'text'=>'Texte','number'=>'Nombre','date'=>'Date',
                                        'select'=>'Liste','multi_select'=>'Multi-liste',
                                        'boolean'=>'Oui/Non','image'=>'Image','images'=>'Images','file'=>'Fichier'
                                    ];
                                    echo $typeLabels[$col['type']] ?? $col['type'];
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $validation = json_decode($col['validation_json'] ?? '{}', true);
                                    echo !empty($validation['required']) ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<span class="text-muted">—</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php if ($col['is_system']): ?>
                                    <span class="badge bg-primary">Système</span>
                                    <?php elseif ($col['is_active']): ?>
                                    <span class="badge bg-success">Actif</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <?php if (\App\Core\Auth::isSuperviseur()): ?>
                                <td class="text-end">
                                    <?php if (!$col['is_system']): ?>
                                    <button class="btn btn-sm btn-outline-secondary btn-edit-col"
                                            data-id="<?= $col['id'] ?>"
                                            data-label="<?= \App\Core\View::e($col['label']) ?>"
                                            data-type="<?= $col['type'] ?>"
                                            data-options="<?= \App\Core\View::e(implode("\n", json_decode($col['options_json'] ?? '[]', true) ?: [])) ?>"
                                            data-required="<?= !empty(json_decode($col['validation_json'] ?? '{}', true)['required']) ? '1' : '' ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" action="/campaigns/<?= $campaign['id'] ?>/columns/<?= $col['id'] ?>/delete" class="d-inline">
                                        <?= \App\Core\CSRF::field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                data-confirm="Désactiver la colonne &laquo;<?= \App\Core\View::e($col['label']) ?>&raquo; ?">
                                            <i class="bi bi-eye-slash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($columns)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="bi bi-layout-three-columns display-6 d-block mb-2 opacity-25"></i>
                                    Aucune colonne. Ajoutez la première ci-contre.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal édition colonne -->
<div class="modal fade" id="editColModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier la colonne</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editColForm" method="POST">
                <div class="modal-body">
                    <?= \App\Core\CSRF::field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Libellé <span class="text-danger">*</span></label>
                        <input type="text" name="label" id="editColLabel" class="form-control" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                        <select name="type" id="editColType" class="form-select" required>
                            <option value="text">Texte</option>
                            <option value="number">Nombre</option>
                            <option value="date">Date</option>
                            <option value="select">Liste déroulante</option>
                            <option value="multi_select">Sélection multiple</option>
                            <option value="boolean">Oui / Non</option>
                            <option value="image">Image (1)</option>
                            <option value="images">Images (multiple)</option>
                            <option value="file">Fichier</option>
                        </select>
                        <div class="form-text text-warning mt-1"><i class="bi bi-exclamation-triangle me-1"></i>Changer le type peut affecter les données existantes.</div>
                    </div>
                    <div id="editOptionsBlock" class="mb-3 d-none">
                        <label class="form-label fw-semibold">Options (une par ligne)</label>
                        <textarea name="options" id="editColOptions" class="form-control" rows="4"></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="required" id="editColRequired" value="1">
                            <label class="form-check-label" for="editColRequired">Champ obligatoire</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Show/hide options for select types
function toggleOptions(typeSelect, optionsBlock) {
    const val = typeSelect.value;
    optionsBlock.classList.toggle('d-none', !['select','multi_select'].includes(val));
}
const addType = document.getElementById('colTypeSelect');
const addOpts = document.getElementById('optionsBlock');
if (addType) { addType.addEventListener('change', () => toggleOptions(addType, addOpts)); }

// Edit modal
document.querySelectorAll('.btn-edit-col').forEach(btn => {
    btn.addEventListener('click', function () {
        const id = this.dataset.id;
        document.getElementById('editColLabel').value = this.dataset.label;
        document.getElementById('editColType').value = this.dataset.type;
        document.getElementById('editColOptions').value = this.dataset.options;
        document.getElementById('editColRequired').checked = this.dataset.required === '1';
        document.getElementById('editColForm').action = '/campaigns/<?= $campaign['id'] ?>/columns/' + id + '/update';
        const editType = document.getElementById('editColType');
        const editOpts = document.getElementById('editOptionsBlock');
        toggleOptions(editType, editOpts);
        editType.addEventListener('change', () => toggleOptions(editType, editOpts));
        new bootstrap.Modal(document.getElementById('editColModal')).show();
    });
});
</script>

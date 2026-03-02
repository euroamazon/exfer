<div class="container-fluid py-4">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="/campaigns/<?= $campaign['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
                <h2 class="mb-0">Anomalies — <?= \App\Core\View::e($campaign['name']) ?></h2>
            </div>
            <p class="text-muted mb-0 small">Suivi et traitement des anomalies détectées</p>
        </div>
    </div>

    <!-- Stats par statut -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 bg-danger bg-opacity-10 h-100">
                <div class="card-body text-center py-3">
                    <h4 class="text-danger"><?= $stats['OPEN'] ?? 0 ?></h4>
                    <p class="mb-0 small">Ouvertes</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 bg-warning bg-opacity-10 h-100">
                <div class="card-body text-center py-3">
                    <h4 class="text-warning"><?= $stats['INVESTIGATION'] ?? 0 ?></h4>
                    <p class="mb-0 small">En cours</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 bg-success bg-opacity-10 h-100">
                <div class="card-body text-center py-3">
                    <h4 class="text-success"><?= $stats['RESOLVED'] ?? 0 ?></h4>
                    <p class="mb-0 small">Résolues</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 bg-secondary bg-opacity-10 h-100">
                <div class="card-body text-center py-3">
                    <h4 class="text-secondary"><?= $stats['REJECTED'] ?? 0 ?></h4>
                    <p class="mb-0 small">Rejetées</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Tous les statuts</option>
                        <option value="OPEN" <?= ($filters['status'] ?? '') === 'OPEN' ? 'selected' : '' ?>>Ouvertes</option>
                        <option value="INVESTIGATION" <?= ($filters['status'] ?? '') === 'INVESTIGATION' ? 'selected' : '' ?>>En investigation</option>
                        <option value="RESOLVED" <?= ($filters['status'] ?? '') === 'RESOLVED' ? 'selected' : '' ?>>Résolues</option>
                        <option value="REJECTED" <?= ($filters['status'] ?? '') === 'REJECTED' ? 'selected' : '' ?>>Rejetées</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="severity" class="form-select form-select-sm">
                        <option value="">Toutes les sévérités</option>
                        <option value="BLOCKING" <?= ($filters['severity'] ?? '') === 'BLOCKING' ? 'selected' : '' ?>>Bloquante</option>
                        <option value="ERROR" <?= ($filters['severity'] ?? '') === 'ERROR' ? 'selected' : '' ?>>Erreur</option>
                        <option value="WARNING" <?= ($filters['severity'] ?? '') === 'WARNING' ? 'selected' : '' ?>>Avertissement</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="type" class="form-select form-select-sm">
                        <option value="">Tous les types</option>
                        <?php
                        $types = [
                            'DOUBLON_CODE'=>'Doublon de code',
                            'CODE_HORS_ROULEAU'=>'Code hors rouleau',
                            'FORMAT_INVALIDE'=>'Format invalide',
                            'SAUT_ROULEAU'=>'Saut rouleau',
                            'INCOHERENCE'=>'Incohérence',
                            'IMAGE_DESIGNATION_MISMATCH'=>'Image/Désignation',
                            'TYPE_CONVERSION_ERROR'=>'Conversion type',
                            'CONFLIT_SYNC'=>'Conflit sync',
                            'CODE_HORS_SEQUENCE'=>'Code hors séquence',
                            'MISSING_CODE'=>'Code manquant',
                        ];
                        foreach ($types as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($filters['type'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-sm btn-primary">Filtrer</button>
                    <a href="?" class="btn btn-sm btn-outline-secondary ms-1">Réinitialiser</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tableau anomalies -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Type</th>
                        <th>Sévérité</th>
                        <th>Description</th>
                        <th>Local</th>
                        <th>Statut</th>
                        <th>Détectée le</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($anomalies as $a): ?>
                    <tr>
                        <td>
                            <span class="badge bg-secondary"><?= \App\Core\View::e($types[$a['type']] ?? $a['type']) ?></span>
                        </td>
                        <td>
                            <?php
                            $sevClasses = ['BLOCKING'=>'danger','ERROR'=>'warning','WARNING'=>'info'];
                            $sevLabels = ['BLOCKING'=>'Bloquant','ERROR'=>'Erreur','WARNING'=>'Avertissement'];
                            $sev = $a['severity'];
                            ?>
                            <span class="badge bg-<?= $sevClasses[$sev] ?? 'secondary' ?>"><?= $sevLabels[$sev] ?? $sev ?></span>
                        </td>
                        <td><?= \App\Core\View::e($a['description']) ?></td>
                        <td>
                            <?php if ($a['code_local'] ?? null): ?>
                            <a href="/campaigns/<?= $campaign['id'] ?>/locations/<?= $a['location_id'] ?>/spreadsheet" class="fw-mono small">
                                <?= \App\Core\View::e($a['code_local']) ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $stClasses = ['OPEN'=>'danger','INVESTIGATION'=>'warning','RESOLVED'=>'success','REJECTED'=>'secondary'];
                            $stLabels = ['OPEN'=>'Ouverte','INVESTIGATION'=>'Investigation','RESOLVED'=>'Résolue','REJECTED'=>'Rejetée'];
                            ?>
                            <span class="badge bg-<?= $stClasses[$a['status']] ?? 'secondary' ?>"><?= $stLabels[$a['status']] ?? $a['status'] ?></span>
                        </td>
                        <td class="small text-muted"><?= \App\Core\View::date($a['detected_at'] ?? $a['created_at'], 'd/m/Y H:i') ?></td>
                        <td class="text-end">
                            <?php if (\App\Core\Auth::isSuperviseur()): ?>
                            <?php if ($a['status'] === 'OPEN'): ?>
                            <button class="btn btn-sm btn-outline-warning btn-investigate"
                                    data-id="<?= $a['id'] ?>">
                                <i class="bi bi-search me-1"></i>Investiguer
                            </button>
                            <?php elseif ($a['status'] === 'INVESTIGATION'): ?>
                            <button class="btn btn-sm btn-outline-success btn-resolve"
                                    data-id="<?= $a['id'] ?>">
                                <i class="bi bi-check-circle me-1"></i>Résoudre
                            </button>
                            <button class="btn btn-sm btn-outline-secondary btn-reject ms-1"
                                    data-id="<?= $a['id'] ?>">
                                <i class="bi bi-x-circle me-1"></i>Rejeter
                            </button>
                            <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($anomalies)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-check-circle display-6 d-block mb-2 opacity-25"></i>
                            Aucune anomalie trouvée.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal notes -->
<div class="modal fade" id="notesModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="notesModalTitle">Notes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="notesForm" method="POST">
                <div class="modal-body">
                    <?= \App\Core\CSRF::field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes / Commentaires</label>
                        <textarea name="notes" class="form-control" rows="4" placeholder="Décrivez les actions effectuées..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" id="notesSubmitBtn" class="btn btn-primary">Confirmer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openNotesModal(title, action, btnLabel, btnClass) {
    document.getElementById('notesModalTitle').textContent = title;
    document.getElementById('notesForm').action = action;
    document.getElementById('notesSubmitBtn').textContent = btnLabel;
    document.getElementById('notesSubmitBtn').className = 'btn ' + btnClass;
    new bootstrap.Modal(document.getElementById('notesModal')).show();
}

document.querySelectorAll('.btn-investigate').forEach(btn => {
    btn.addEventListener('click', function () {
        openNotesModal('Passer en investigation', '/anomalies/' + this.dataset.id + '/investigate', 'Investiguer', 'btn-warning');
    });
});
document.querySelectorAll('.btn-resolve').forEach(btn => {
    btn.addEventListener('click', function () {
        openNotesModal('Résoudre l\'anomalie', '/anomalies/' + this.dataset.id + '/resolve', 'Résoudre', 'btn-success');
    });
});
document.querySelectorAll('.btn-reject').forEach(btn => {
    btn.addEventListener('click', function () {
        openNotesModal('Rejeter l\'anomalie', '/anomalies/' + this.dataset.id + '/reject', 'Rejeter', 'btn-secondary');
    });
});
</script>

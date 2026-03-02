<div class="container-fluid py-4">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Prestations</h2>
    </div>

    <div class="row g-4">
        <!-- Formulaire d'ajout -->
        <?php if (\App\Core\Auth::isAdmin()): ?>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white pt-3">
                    <h6 class="card-title mb-0"><i class="bi bi-plus-circle me-2"></i>Nouvelle prestation</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger py-2 small">
                        <?php foreach ($errors as $e): ?><div><?= \App\Core\View::e($e) ?></div><?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <form method="POST" action="/services">
                        <?= \App\Core\CSRF::field() ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nom <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="Ex : Inventaire annuel" required maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Code</label>
                            <input type="text" name="code" class="form-control fw-mono" placeholder="INV-2025" maxlength="30">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="2" maxlength="500"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-plus me-1"></i>Ajouter
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Liste -->
        <div class="col-md-<?= \App\Core\Auth::isAdmin() ? '8' : '12' ?>">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white pt-3">
                    <h6 class="card-title mb-0"><i class="bi bi-briefcase me-2"></i>Prestations (<?= count($services) ?>)</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nom</th>
                                <th>Code</th>
                                <th>Description</th>
                                <th>Campagnes</th>
                                <th>Statut</th>
                                <?php if (\App\Core\Auth::isAdmin()): ?><th></th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($services as $svc): ?>
                            <tr>
                                <td class="fw-semibold"><?= \App\Core\View::e($svc['name']) ?></td>
                                <td><span class="badge bg-secondary fw-mono"><?= \App\Core\View::e($svc['code'] ?? '—') ?></span></td>
                                <td class="text-muted small"><?= \App\Core\View::e($svc['description'] ?? '—') ?></td>
                                <td><?= (int)($svc['campaign_count'] ?? 0) ?></td>
                                <td><?= $svc['is_active'] ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-secondary">Inactif</span>' ?></td>
                                <?php if (\App\Core\Auth::isAdmin()): ?>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-secondary btn-edit-svc"
                                            data-id="<?= $svc['id'] ?>"
                                            data-name="<?= \App\Core\View::e($svc['name']) ?>"
                                            data-code="<?= \App\Core\View::e($svc['code'] ?? '') ?>"
                                            data-description="<?= \App\Core\View::e($svc['description'] ?? '') ?>"
                                            data-active="<?= $svc['is_active'] ? '1' : '0' ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" action="/services/<?= $svc['id'] ?>/delete" class="d-inline">
                                        <?= \App\Core\CSRF::field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                data-confirm="Supprimer la prestation &laquo;<?= \App\Core\View::e($svc['name']) ?>&raquo; ?"
                                                <?= (int)($svc['campaign_count'] ?? 0) > 0 ? 'disabled title="Prestation utilisée"' : '' ?>>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($services)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">Aucune prestation.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal édition -->
<div class="modal fade" id="editSvcModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier la prestation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editSvcForm" method="POST">
                <div class="modal-body">
                    <?= \App\Core\CSRF::field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nom <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editSvcName" class="form-control" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Code</label>
                        <input type="text" name="code" id="editSvcCode" class="form-control fw-mono" maxlength="30">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" id="editSvcDesc" class="form-control" rows="2" maxlength="500"></textarea>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="editSvcActive" value="1">
                        <label class="form-check-label" for="editSvcActive">Active</label>
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
document.querySelectorAll('.btn-edit-svc').forEach(btn => {
    btn.addEventListener('click', function () {
        document.getElementById('editSvcName').value = this.dataset.name;
        document.getElementById('editSvcCode').value = this.dataset.code;
        document.getElementById('editSvcDesc').value = this.dataset.description;
        document.getElementById('editSvcActive').checked = this.dataset.active === '1';
        document.getElementById('editSvcForm').action = '/services/' + this.dataset.id + '/update';
        new bootstrap.Modal(document.getElementById('editSvcModal')).show();
    });
});
</script>

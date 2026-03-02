<div class="container-fluid py-4">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="/campaigns/<?= $campaign['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
                <h2 class="mb-0">Sites — <?= \App\Core\View::e($campaign['name']) ?></h2>
            </div>
            <p class="text-muted mb-0 small">Gérer les sites géographiques de la campagne</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- Formulaire d'ajout -->
        <?php if (\App\Core\Auth::isSuperviseur()): ?>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white pt-3">
                    <h6 class="card-title mb-0"><i class="bi bi-plus-circle me-2"></i>Nouveau site</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="/campaigns/<?= $campaign['id'] ?>/sites">
                        <?= \App\Core\CSRF::field() ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nom <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="Siège social" required maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Code</label>
                            <input type="text" name="code" class="form-control fw-mono" placeholder="SIEGE" maxlength="20">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Adresse</label>
                            <textarea name="address" class="form-control" rows="2" maxlength="500" placeholder="Adresse du site..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-plus me-1"></i>Ajouter
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Liste des sites -->
        <div class="col-md-<?= \App\Core\Auth::isSuperviseur() ? '8' : '12' ?>">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white pt-3">
                    <h6 class="card-title mb-0"><i class="bi bi-building me-2"></i>Sites (<?= count($sites) ?>)</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nom</th>
                                <th>Code</th>
                                <th>Adresse</th>
                                <th>Locaux</th>
                                <th>Articles</th>
                                <?php if (\App\Core\Auth::isSuperviseur()): ?><th></th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sites as $site): ?>
                            <tr id="site-<?= $site['id'] ?>">
                                <td class="fw-semibold"><?= \App\Core\View::e($site['name']) ?></td>
                                <td><span class="badge bg-secondary fw-mono"><?= \App\Core\View::e($site['code'] ?? '—') ?></span></td>
                                <td class="text-muted small"><?= \App\Core\View::e($site['address'] ?? '—') ?></td>
                                <td><?= (int)($site['location_count'] ?? 0) ?></td>
                                <td><?= (int)($site['items_count'] ?? 0) ?></td>
                                <?php if (\App\Core\Auth::isSuperviseur()): ?>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-secondary btn-edit-site"
                                            data-id="<?= $site['id'] ?>"
                                            data-name="<?= \App\Core\View::e($site['name']) ?>"
                                            data-code="<?= \App\Core\View::e($site['code'] ?? '') ?>"
                                            data-address="<?= \App\Core\View::e($site['address'] ?? '') ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" action="/campaigns/<?= $campaign['id'] ?>/sites/<?= $site['id'] ?>/delete" class="d-inline">
                                        <?= \App\Core\CSRF::field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                data-confirm="Supprimer le site &laquo;<?= \App\Core\View::e($site['name']) ?>&raquo; ?"
                                                <?= (int)($site['location_count'] ?? 0) > 0 ? 'disabled title="Site non vide"' : '' ?>>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($sites)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-building display-6 d-block mb-2 opacity-25"></i>
                                    Aucun site. Ajoutez le premier site ci-contre.
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

<!-- Modal édition site -->
<div class="modal fade" id="editSiteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier le site</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editSiteForm" method="POST">
                <div class="modal-body">
                    <?= \App\Core\CSRF::field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nom <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editName" class="form-control" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Code</label>
                        <input type="text" name="code" id="editCode" class="form-control fw-mono" maxlength="20">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Adresse</label>
                        <textarea name="address" id="editAddress" class="form-control" rows="2" maxlength="500"></textarea>
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
document.querySelectorAll('.btn-edit-site').forEach(btn => {
    btn.addEventListener('click', function () {
        const id = this.dataset.id;
        document.getElementById('editName').value = this.dataset.name;
        document.getElementById('editCode').value = this.dataset.code;
        document.getElementById('editAddress').value = this.dataset.address;
        document.getElementById('editSiteForm').action = '/campaigns/<?= $campaign['id'] ?>/sites/' + id + '/update';
        new bootstrap.Modal(document.getElementById('editSiteModal')).show();
    });
});
</script>

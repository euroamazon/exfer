<div class="container-fluid py-4">
    <!-- En-tête -->
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="/campaigns/<?= $campaign['id'] ?>/import/<?= $job['id'] ?>/mapping" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
        <h2 class="mb-0">Aperçu de l'import</h2>
        <?php if ($job['is_dry_run']): ?>
        <span class="badge bg-info">Simulation</span>
        <?php endif; ?>
    </div>

    <!-- Étapes -->
    <div class="d-flex align-items-center mb-4">
        <span class="badge bg-success rounded-pill px-3 py-2 me-2"><i class="bi bi-check"></i></span>
        <span class="text-muted me-3">Téléverser</span>
        <div class="flex-grow-1 border-top"></div>
        <span class="badge bg-success rounded-pill px-3 py-2 mx-2"><i class="bi bi-check"></i></span>
        <span class="text-muted me-3">Mapping</span>
        <div class="flex-grow-1 border-top"></div>
        <span class="badge bg-primary rounded-pill px-3 py-2 mx-2">3</span>
        <span class="fw-semibold me-3">Aperçu</span>
        <div class="flex-grow-1 border-top"></div>
        <span class="badge bg-light text-muted rounded-pill px-3 py-2 mx-2">4</span>
        <span class="text-muted">Résultat</span>
    </div>

    <!-- Stats validation -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 bg-primary bg-opacity-10">
                <div class="card-body text-center py-3">
                    <h4 class="text-primary"><?= $stats['total'] ?></h4>
                    <p class="mb-0 small">Lignes totales</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 bg-success bg-opacity-10">
                <div class="card-body text-center py-3">
                    <h4 class="text-success"><?= $stats['valid'] ?></h4>
                    <p class="mb-0 small">Valides</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 bg-danger bg-opacity-10">
                <div class="card-body text-center py-3">
                    <h4 class="text-danger"><?= $stats['errors'] ?></h4>
                    <p class="mb-0 small">Erreurs</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 bg-warning bg-opacity-10">
                <div class="card-body text-center py-3">
                    <h4 class="text-warning"><?= $stats['warnings'] ?? 0 ?></h4>
                    <p class="mb-0 small">Avertissements</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Erreurs -->
    <?php if (!empty($errors)): ?>
    <div class="card border-0 shadow-sm border-start border-danger border-3 mb-4">
        <div class="card-header bg-white pt-3">
            <h6 class="card-title mb-0 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Erreurs (<?= count($errors) ?>)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Ligne</th><th>Colonne</th><th>Erreur</th><th>Valeur</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($errors, 0, 50) as $err): ?>
                        <tr class="table-danger">
                            <td><?= $err['row'] ?? '—' ?></td>
                            <td><code><?= \App\Core\View::e($err['field'] ?? '—') ?></code></td>
                            <td><?= \App\Core\View::e($err['message'] ?? '—') ?></td>
                            <td class="fw-mono small"><?= \App\Core\View::e($err['value'] ?? '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (count($errors) > 50): ?>
                        <tr><td colspan="4" class="text-center text-muted">... et <?= count($errors) - 50 ?> autres erreurs</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Aperçu données -->
    <?php if (!empty($previewRows)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white pt-3">
            <h6 class="card-title mb-0"><i class="bi bi-eye me-2"></i>Aperçu des données (<?= count($previewRows) ?> premières lignes)</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <?php foreach (array_keys($previewRows[0] ?? []) as $col): ?>
                        <th><?= \App\Core\View::e($col) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($previewRows as $i => $row): ?>
                    <tr>
                        <td class="text-muted small"><?= $i + 1 ?></td>
                        <?php foreach ($row as $val): ?>
                        <td class="small"><?= \App\Core\View::e($val ?? '') ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="d-flex gap-2">
        <?php if ($stats['errors'] === 0 || $job['is_dry_run']): ?>
        <form method="POST" action="/campaigns/<?= $campaign['id'] ?>/import/<?= $job['id'] ?>/execute">
            <?= \App\Core\CSRF::field() ?>
            <button type="submit" class="btn btn-success"
                    data-confirm="<?= $job['is_dry_run'] ? 'Exécuter la simulation ?' : 'Lancer l\'import réel ?' ?>">
                <i class="bi bi-play-circle me-1"></i>
                <?= $job['is_dry_run'] ? 'Exécuter la simulation' : 'Lancer l\'import' ?>
                <?php if ($stats['valid'] > 0): ?>
                (<?= $stats['valid'] ?> lignes)
                <?php endif; ?>
            </button>
        </form>
        <?php endif; ?>
        <a href="/campaigns/<?= $campaign['id'] ?>/import" class="btn btn-outline-secondary">Recommencer</a>
    </div>
</div>

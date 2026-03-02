<div class="container-fluid py-4">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="/campaigns/<?= $campaign['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
                <h2 class="mb-0">Rapport d'import</h2>
                <?php if ($job['is_dry_run']): ?>
                <span class="badge bg-info">Simulation</span>
                <?php endif; ?>
            </div>
            <p class="text-muted mb-0 small">
                Fichier : <strong><?= \App\Core\View::e($job['filename']) ?></strong> —
                Mode : <strong><?= $job['mode'] ?></strong> —
                <?= $job['finished_at'] ? 'Terminé le ' . \App\Core\View::date($job['finished_at'], 'd/m/Y H:i') : 'En cours...' ?>
            </p>
        </div>
        <div>
            <a href="/campaigns/<?= $campaign['id'] ?>/import" class="btn btn-outline-primary">
                <i class="bi bi-upload me-1"></i>Nouvel import
            </a>
        </div>
    </div>

    <!-- Étapes -->
    <div class="d-flex align-items-center mb-4">
        <?php
        $steps = ['Téléverser', 'Mapping', 'Aperçu', 'Résultat'];
        foreach ($steps as $i => $step):
        ?>
        <span class="badge bg-success rounded-pill px-3 py-2 me-2"><i class="bi bi-check"></i></span>
        <span class="<?= $i === 3 ? 'fw-semibold' : 'text-muted' ?> me-3"><?= $step ?></span>
        <?php if ($i < 3): ?><div class="flex-grow-1 border-top"></div><?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Statut global -->
    <?php
    $status = $job['status'];
    $statusInfo = [
        'DONE' => ['success', 'check-circle', 'Import terminé avec succès'],
        'FAILED' => ['danger', 'x-circle', 'Import échoué'],
        'PROCESSING' => ['warning', 'hourglass-split', 'Import en cours...'],
        'PENDING' => ['secondary', 'clock', 'En attente'],
    ][$status] ?? ['secondary', 'question-circle', $status];
    ?>
    <div class="alert alert-<?= $statusInfo[0] ?> d-flex align-items-center mb-4">
        <i class="bi bi-<?= $statusInfo[1] ?> me-2 fs-5"></i>
        <strong><?= $statusInfo[2] ?></strong>
    </div>

    <!-- Stats (passed from controller as decoded array) -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 bg-primary bg-opacity-10">
                <div class="card-body text-center py-3">
                    <h4 class="text-primary"><?= $stats['total'] ?? 0 ?></h4>
                    <p class="mb-0 small">Lignes traitées</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 bg-success bg-opacity-10">
                <div class="card-body text-center py-3">
                    <h4 class="text-success"><?= $stats['inserted'] ?? 0 ?></h4>
                    <p class="mb-0 small">Insérés</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 bg-info bg-opacity-10">
                <div class="card-body text-center py-3">
                    <h4 class="text-info"><?= $stats['updated'] ?? 0 ?></h4>
                    <p class="mb-0 small">Mis à jour</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 bg-danger bg-opacity-10">
                <div class="card-body text-center py-3">
                    <h4 class="text-danger"><?= $stats['errors'] ?? 0 ?></h4>
                    <p class="mb-0 small">Erreurs</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Log erreurs (variable $errors passée par le contrôleur) -->
    <?php if (!empty($errors)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white pt-3">
            <h6 class="card-title mb-0 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Journal des erreurs</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Ligne</th><th>Code</th><th>Erreur</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($errors, 0, 100) as $err): ?>
                        <tr class="table-danger">
                            <td><?= $err['row'] ?? '—' ?></td>
                            <td><code><?= \App\Core\View::e($err['code'] ?? '—') ?></code></td>
                            <td><?= \App\Core\View::e(implode(', ', (array)($err['errors'] ?? $err['message'] ?? '—'))) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($job['status'] === 'DONE' && !$job['is_dry_run']): ?>
    <a href="/campaigns/<?= $campaign['id'] ?>/locations" class="btn btn-primary">
        <i class="bi bi-door-open me-1"></i>Voir les locaux
    </a>
    <?php elseif ($job['is_dry_run']): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        C'était une simulation. Les données n'ont pas été modifiées.
        <a href="/campaigns/<?= $campaign['id'] ?>/import" class="btn btn-sm btn-info ms-3">
            Importer pour de vrai
        </a>
    </div>
    <?php endif; ?>
</div>

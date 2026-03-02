<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="bi bi-journal-check me-2"></i>Campagnes d'inventaire</h2>
            <p class="text-muted mb-0"><?= count($campaigns) ?> campagne(s) accessible(s)</p>
        </div>
        <?php if (\App\Core\Auth::isSuperviseur()): ?>
        <a href="/campaigns/create" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Nouvelle campagne
        </a>
        <?php endif; ?>
    </div>

    <?php if (empty($campaigns)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-journal-plus fs-1 d-block mb-3"></i>
        <h5>Aucune campagne disponible</h5>
        <?php if (\App\Core\Auth::isSuperviseur()): ?>
        <a href="/campaigns/create" class="btn btn-primary mt-2">Créer une campagne</a>
        <?php else: ?>
        <p>Aucune campagne ne vous a encore été assignée.</p>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="row g-4">
        <?php foreach ($campaigns as $c):
            $config = json_decode($c['config'] ?? '{}', true) ?: [];
        ?>
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="card-title mb-0"><?= \App\Core\View::e($c['name']) ?></h5>
                        <?= \App\Core\View::statusBadge($c['status']) ?>
                    </div>
                    <?php if ($c['service_name']): ?>
                    <p class="text-muted small mb-2">
                        <i class="bi bi-briefcase me-1"></i><?= \App\Core\View::e($c['service_name']) ?>
                    </p>
                    <?php endif; ?>
                    <?php if ($c['description']): ?>
                    <p class="text-muted small mb-2"><?= \App\Core\View::e($c['description']) ?></p>
                    <?php endif; ?>
                    <div class="d-flex gap-3 text-muted small mb-3">
                        <span><i class="bi bi-key me-1"></i>Codes <?= $config['code_type'] ?? 'NUMERIC' ?> <?= $config['code_length'] ?? 6 ?> c.</span>
                        <?php if (!empty($config['label_roll_enabled'])): ?>
                        <span class="text-success"><i class="bi bi-upc me-1"></i>Rouleaux</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer bg-white border-0 d-flex gap-2 flex-wrap">
                    <a href="/campaigns/<?= $c['id'] ?>" class="btn btn-sm btn-primary flex-fill">
                        <i class="bi bi-eye me-1"></i>Accéder
                    </a>
                    <?php if (\App\Core\Auth::isSuperviseur()): ?>
                    <a href="/campaigns/<?= $c['id'] ?>/edit" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

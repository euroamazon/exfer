<div class="container-fluid py-4">
    <!-- En-tête campagne -->
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="/campaigns" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
                <h2 class="mb-0"><?= \App\Core\View::e($campaign['name']) ?></h2>
                <?= \App\Core\View::statusBadge($campaign['status']) ?>
            </div>
            <p class="text-muted mb-0">
                <?php if ($campaign['service_name']): ?>
                <span class="me-3"><i class="bi bi-briefcase me-1"></i><?= \App\Core\View::e($campaign['service_name']) ?></span>
                <?php endif; ?>
                <?php if ($campaign['started_at']): ?>
                <span class="me-3"><i class="bi bi-play me-1"></i>Démarrée le <?= \App\Core\View::date($campaign['started_at'], 'd/m/Y') ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if (\App\Core\Auth::isSuperviseur()): ?>
                <?php if ($campaign['status'] === 'DRAFT'): ?>
                <form method="POST" action="/campaigns/<?= $campaign['id'] ?>/start">
                    <?= \App\Core\CSRF::field() ?>
                    <button type="submit" class="btn btn-success" data-confirm="Démarrer la campagne ?">
                        <i class="bi bi-play-circle me-1"></i>Démarrer
                    </button>
                </form>
                <?php elseif ($campaign['status'] === 'ACTIVE'): ?>
                <form method="POST" action="/campaigns/<?= $campaign['id'] ?>/close">
                    <?= \App\Core\CSRF::field() ?>
                    <button type="submit" class="btn btn-warning" data-confirm="Clôturer la campagne ? Cette action est irréversible.">
                        <i class="bi bi-lock me-1"></i>Clôturer
                    </button>
                </form>
                <?php endif; ?>
                <a href="/campaigns/<?= $campaign['id'] ?>/edit" class="btn btn-outline-secondary">
                    <i class="bi bi-pencil me-1"></i>Modifier
                </a>
                <form method="POST" action="/campaigns/<?= $campaign['id'] ?>/duplicate">
                    <?= \App\Core\CSRF::field() ?>
                    <button type="submit" class="btn btn-outline-secondary" data-confirm="Dupliquer cette campagne ?">
                        <i class="bi bi-copy me-1"></i>Dupliquer
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats rapides -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 bg-primary bg-opacity-10 h-100">
                <div class="card-body text-center">
                    <h3 class="text-primary"><?= $stats['total_locations'] ?></h3>
                    <p class="mb-0 small">Locaux</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 bg-success bg-opacity-10 h-100">
                <div class="card-body text-center">
                    <h3 class="text-success"><?= $stats['validated_locations'] ?></h3>
                    <p class="mb-0 small">Locaux validés</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 bg-info bg-opacity-10 h-100">
                <div class="card-body text-center">
                    <h3 class="text-info"><?= number_format($stats['total_items'], 0, ',', ' ') ?></h3>
                    <p class="mb-0 small">Articles</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 <?= $stats['open_anomalies'] > 0 ? 'bg-warning' : 'bg-light' ?> bg-opacity-10 h-100">
                <div class="card-body text-center">
                    <h3 class="<?= $stats['open_anomalies'] > 0 ? 'text-warning' : '' ?>"><?= $stats['open_anomalies'] ?></h3>
                    <p class="mb-0 small">Anomalies ouvertes</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <a href="/campaigns/<?= $campaign['id'] ?>/locations" class="card border-0 shadow-sm text-decoration-none h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                        <i class="bi bi-door-open text-primary fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">Locaux & Saisie</div>
                        <div class="text-muted small">Gérer les locaux et saisir les articles</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="/campaigns/<?= $campaign['id'] ?>/anomalies" class="card border-0 shadow-sm text-decoration-none h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="bg-warning bg-opacity-10 rounded-3 p-3">
                        <i class="bi bi-exclamation-triangle text-warning fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">Anomalies</div>
                        <div class="text-muted small"><?= $stats['open_anomalies'] ?> en attente de traitement</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="/campaigns/<?= $campaign['id'] ?>/export" class="card border-0 shadow-sm text-decoration-none h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="bg-success bg-opacity-10 rounded-3 p-3">
                        <i class="bi bi-download text-success fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">Exporter</div>
                        <div class="text-muted small">CSV, XLSX, PDF</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Locaux -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center pt-3">
            <h5 class="card-title mb-0"><i class="bi bi-door-open me-2"></i>Locaux</h5>
            <div class="d-flex gap-2">
                <a href="/campaigns/<?= $campaign['id'] ?>/sites" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-building me-1"></i>Gérer les sites
                </a>
                <?php if ($campaign['status'] === 'ACTIVE'): ?>
                <a href="/campaigns/<?= $campaign['id'] ?>/locations/create" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus me-1"></i>Nouveau local
                </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Code Local</th>
                        <th>Désignation</th>
                        <th>Site</th>
                        <th>Articles</th>
                        <th>Statut</th>
                        <th>Anomalies</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($locations as $loc): ?>
                    <tr>
                        <td class="fw-mono fw-semibold"><?= \App\Core\View::e($loc['code_local']) ?></td>
                        <td><?= \App\Core\View::e($loc['designation_local'] ?? '—') ?></td>
                        <td><?= \App\Core\View::e($loc['site_name'] ?? '—') ?></td>
                        <td><?= (int)$loc['items_count'] ?></td>
                        <td><?= \App\Core\View::statusBadge($loc['status']) ?></td>
                        <td>
                            <?php if ((int)$loc['anomaly_count'] > 0): ?>
                            <span class="badge bg-warning text-dark"><?= $loc['anomaly_count'] ?></span>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="/campaigns/<?= $campaign['id'] ?>/locations/<?= $loc['id'] ?>/spreadsheet"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-table me-1"></i>Saisie
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($locations)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Aucun local. <a href="/campaigns/<?= $campaign['id'] ?>/locations/create">Créer un local</a></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

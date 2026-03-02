<div class="container-fluid py-4">
    <div class="row g-4 mb-4">
        <!-- Stat cards -->
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 bg-primary bg-opacity-10 rounded-3 p-3 me-3">
                            <i class="bi bi-journal-check text-primary fs-4"></i>
                        </div>
                        <div>
                            <p class="text-muted small mb-1">Campagnes actives</p>
                            <h3 class="mb-0 fw-bold"><?= (int)$stats['campaigns_active'] ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 bg-success bg-opacity-10 rounded-3 p-3 me-3">
                            <i class="bi bi-box-seam text-success fs-4"></i>
                        </div>
                        <div>
                            <p class="text-muted small mb-1">Articles inventoriés</p>
                            <h3 class="mb-0 fw-bold"><?= number_format((int)$stats['items_total'], 0, ',', ' ') ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 bg-warning bg-opacity-10 rounded-3 p-3 me-3">
                            <i class="bi bi-exclamation-triangle text-warning fs-4"></i>
                        </div>
                        <div>
                            <p class="text-muted small mb-1">Anomalies ouvertes</p>
                            <h3 class="mb-0 fw-bold"><?= (int)$stats['anomalies_open'] ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 bg-info bg-opacity-10 rounded-3 p-3 me-3">
                            <i class="bi bi-people text-info fs-4"></i>
                        </div>
                        <div>
                            <p class="text-muted small mb-1">Utilisateurs actifs</p>
                            <h3 class="mb-0 fw-bold"><?= (int)$stats['users_active'] ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Avancement des campagnes -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pt-3">
                    <h5 class="card-title mb-0"><i class="bi bi-bar-chart-line me-2"></i>Avancement des campagnes actives</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($campaignProgress)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-journal-plus fs-1 d-block mb-2"></i>
                        Aucune campagne active. <a href="/campaigns/create">Créer une campagne</a>
                    </div>
                    <?php else: ?>
                    <?php foreach ($campaignProgress as $cp): ?>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <div>
                                <a href="/campaigns/<?= $cp['campaign']['id'] ?>" class="fw-semibold text-decoration-none">
                                    <?= \App\Core\View::e($cp['campaign']['name']) ?>
                                </a>
                                <span class="badge bg-secondary ms-2 small"><?= $cp['campaign']['service_name'] ?? '' ?></span>
                            </div>
                            <span class="badge <?= $cp['progress'] >= 100 ? 'bg-success' : 'bg-primary' ?>">
                                <?= $cp['progress'] ?>%
                            </span>
                        </div>
                        <div class="progress mb-1" style="height: 8px;">
                            <div class="progress-bar <?= $cp['progress'] >= 100 ? 'bg-success' : '' ?>"
                                 role="progressbar"
                                 style="width: <?= $cp['progress'] ?>%">
                            </div>
                        </div>
                        <div class="d-flex gap-3 text-muted small">
                            <span><i class="bi bi-door-open me-1"></i><?= $cp['validated_locations'] ?>/<?= $cp['total_locations'] ?> locaux</span>
                            <span><i class="bi bi-box me-1"></i><?= $cp['total_items'] ?> articles</span>
                            <?php if ($cp['open_anomalies'] > 0): ?>
                            <span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= $cp['open_anomalies'] ?> anomalies</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Activité récente -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pt-3">
                    <h5 class="card-title mb-0"><i class="bi bi-clock-history me-2"></i>Activité récente</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($recentActivity as $activity): ?>
                        <li class="list-group-item py-2 px-3">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-activity text-muted"></i>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="small fw-semibold"><?= \App\Core\View::e($activity['action']) ?></div>
                                    <div class="small text-muted"><?= \App\Core\View::e($activity['user_name'] ?? 'Système') ?></div>
                                </div>
                                <div class="text-muted small text-nowrap">
                                    <?= date('d/m H:i', strtotime($activity['created_at'])) ?>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                        <?php if (empty($recentActivity)): ?>
                        <li class="list-group-item text-center text-muted py-4">Aucune activité récente</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Toutes les campagnes -->
    <?php if (!empty($campaigns)): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-3">
                    <h5 class="card-title mb-0"><i class="bi bi-journal-check me-2"></i>Mes campagnes</h5>
                    <a href="/campaigns/create" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus me-1"></i>Nouvelle campagne
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nom</th>
                                <th>Prestation</th>
                                <th>Statut</th>
                                <th>Créée le</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($campaigns as $c): ?>
                            <tr>
                                <td class="fw-semibold"><a href="/campaigns/<?= $c['id'] ?>" class="text-decoration-none"><?= \App\Core\View::e($c['name']) ?></a></td>
                                <td><?= \App\Core\View::e($c['service_name'] ?? '—') ?></td>
                                <td><?= \App\Core\View::statusBadge($c['status']) ?></td>
                                <td><?= \App\Core\View::date($c['created_at'], 'd/m/Y') ?></td>
                                <td class="text-end"><a href="/campaigns/<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary">Accéder</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

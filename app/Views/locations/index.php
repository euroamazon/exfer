<div class="container-fluid py-4">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="/campaigns/<?= $campaign['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
                <h2 class="mb-0">Locaux — <?= \App\Core\View::e($campaign['name']) ?></h2>
            </div>
            <p class="text-muted mb-0 small">Gestion des locaux de la campagne</p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($campaign['status'] === 'ACTIVE'): ?>
            <a href="/campaigns/<?= $campaign['id'] ?>/locations/create" class="btn btn-primary">
                <i class="bi bi-plus me-1"></i>Nouveau local
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2">
            <div class="row g-2 align-items-center">
                <div class="col-md-4">
                    <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Rechercher un local...">
                </div>
                <div class="col-md-3">
                    <select id="statusFilter" class="form-select form-select-sm">
                        <option value="">Tous les statuts</option>
                        <option value="DRAFT">Brouillon</option>
                        <option value="IN_PROGRESS">En cours</option>
                        <option value="VALIDATED">Validé</option>
                        <option value="NEEDS_REVIEW">À revoir</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="siteFilter" class="form-select form-select-sm">
                        <option value="">Tous les sites</option>
                        <?php foreach ($sites as $site): ?>
                        <option value="<?= $site['id'] ?>"><?= \App\Core\View::e($site['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Tableau -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="locationsTable">
                <thead class="table-light">
                    <tr>
                        <th>Code Local</th>
                        <th>Désignation</th>
                        <th>Site</th>
                        <th>Articles</th>
                        <th>Statut</th>
                        <th>Anomalies</th>
                        <th>Validé le</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($locations as $loc): ?>
                    <tr data-status="<?= $loc['status'] ?>" data-site="<?= $loc['site_id'] ?>">
                        <td class="fw-mono fw-semibold"><?= \App\Core\View::e($loc['code_local']) ?></td>
                        <td><?= \App\Core\View::e($loc['designation_local'] ?? '—') ?></td>
                        <td><?= \App\Core\View::e($loc['site_name'] ?? '—') ?></td>
                        <td><?= (int)$loc['items_count'] ?></td>
                        <td><?= \App\Core\View::statusBadge($loc['status']) ?></td>
                        <td>
                            <?php if ((int)($loc['anomaly_count'] ?? 0) > 0): ?>
                            <a href="/campaigns/<?= $campaign['id'] ?>/anomalies?location_id=<?= $loc['id'] ?>" class="badge bg-warning text-dark text-decoration-none">
                                <?= $loc['anomaly_count'] ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= $loc['validated_at'] ? \App\Core\View::date($loc['validated_at'], 'd/m/Y') : '—' ?></td>
                        <td class="text-end">
                            <a href="/campaigns/<?= $campaign['id'] ?>/locations/<?= $loc['id'] ?>/spreadsheet" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-table me-1"></i>Saisie
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($locations)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="bi bi-door-open display-6 d-block mb-2 opacity-25"></i>
                            Aucun local pour l'instant.
                            <?php if ($campaign['status'] === 'ACTIVE'): ?>
                            <a href="/campaigns/<?= $campaign['id'] ?>/locations/create">Créer le premier local</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const search = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const siteFilter = document.getElementById('siteFilter');
    const rows = document.querySelectorAll('#locationsTable tbody tr[data-status]');

    function applyFilters() {
        const q = search.value.toLowerCase();
        const st = statusFilter.value;
        const si = siteFilter.value;
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const status = row.dataset.status;
            const site = row.dataset.site;
            const show = (!q || text.includes(q)) && (!st || status === st) && (!si || site === si);
            row.style.display = show ? '' : 'none';
        });
    }
    search.addEventListener('input', applyFilters);
    statusFilter.addEventListener('change', applyFilters);
    siteFilter.addEventListener('change', applyFilters);
});
</script>

<div class="container-fluid py-4">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Organisations</h2>
        <a href="/admin/organizations/create" class="btn btn-primary">
            <i class="bi bi-plus me-1"></i>Nouvelle organisation
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nom</th>
                        <th>Slug</th>
                        <th>Utilisateurs</th>
                        <th>Campagnes</th>
                        <th>Statut</th>
                        <th>Créée le</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($organizations as $org): ?>
                    <tr>
                        <td class="fw-semibold"><?= \App\Core\View::e($org['name']) ?></td>
                        <td><code class="text-muted"><?= \App\Core\View::e($org['slug']) ?></code></td>
                        <td><?= (int)($org['user_count'] ?? 0) ?></td>
                        <td><?= (int)($org['campaign_count'] ?? 0) ?></td>
                        <td><?= $org['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                        <td class="small text-muted"><?= \App\Core\View::date($org['created_at'], 'd/m/Y') ?></td>
                        <td class="text-end">
                            <a href="/admin/organizations/<?= $org['id'] ?>/edit" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($organizations)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">Aucune organisation.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="container-fluid py-4">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Utilisateurs</h2>
        <?php if (\App\Core\Auth::isSuperviseur()): ?>
        <a href="/users/create" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i>Nouvel utilisateur
        </a>
        <?php endif; ?>
    </div>

    <!-- Filtres -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2">
            <div class="row g-2">
                <div class="col-md-4">
                    <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Rechercher par nom ou email...">
                </div>
                <div class="col-md-3">
                    <select id="roleFilter" class="form-select form-select-sm">
                        <option value="">Tous les rôles</option>
                        <option value="ADMIN">Administrateur</option>
                        <option value="SUPERVISEUR">Superviseur</option>
                        <option value="AGENT">Agent</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="statusFilter" class="form-select form-select-sm">
                        <option value="">Tous</option>
                        <option value="1">Actifs</option>
                        <option value="0">Inactifs</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Tableau -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="usersTable">
                <thead class="table-light">
                    <tr>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th>Dernière connexion</th>
                        <?php if (\App\Core\Auth::isSuperviseur()): ?><th></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr data-role="<?= $u['role'] ?>" data-active="<?= $u['is_active'] ? '1' : '0' ?>">
                        <td class="fw-semibold">
                            <?= \App\Core\View::e($u['name']) ?>
                            <?php if ($u['id'] === \App\Core\Auth::id()): ?>
                            <span class="badge bg-primary ms-1">Vous</span>
                            <?php endif; ?>
                        </td>
                        <td><?= \App\Core\View::e($u['email']) ?></td>
                        <td>
                            <?php
                            $roleColors = ['ADMIN'=>'danger','SUPERVISEUR'=>'warning','AGENT'=>'info'];
                            $roleLabels = ['ADMIN'=>'Administrateur','SUPERVISEUR'=>'Superviseur','AGENT'=>'Agent'];
                            ?>
                            <span class="badge bg-<?= $roleColors[$u['role']] ?? 'secondary' ?>">
                                <?= $roleLabels[$u['role']] ?? $u['role'] ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($u['is_active']): ?>
                            <span class="badge bg-success">Actif</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Inactif</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted">
                            <?= $u['last_login_at'] ? \App\Core\View::date($u['last_login_at'], 'd/m/Y H:i') : 'Jamais' ?>
                        </td>
                        <?php if (\App\Core\Auth::isSuperviseur()): ?>
                        <td class="text-end">
                            <a href="/users/<?= $u['id'] ?>/edit" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">Aucun utilisateur.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const search = document.getElementById('searchInput');
const roleFilter = document.getElementById('roleFilter');
const statusFilter = document.getElementById('statusFilter');
const rows = document.querySelectorAll('#usersTable tbody tr[data-role]');

function applyFilters() {
    const q = search.value.toLowerCase();
    const role = roleFilter.value;
    const active = statusFilter.value;
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const show = (!q || text.includes(q))
                  && (!role || row.dataset.role === role)
                  && (!active || row.dataset.active === active);
        row.style.display = show ? '' : 'none';
    });
}
search.addEventListener('input', applyFilters);
roleFilter.addEventListener('change', applyFilters);
statusFilter.addEventListener('change', applyFilters);
</script>

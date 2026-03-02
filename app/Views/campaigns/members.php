<div class="container-fluid py-4">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="/campaigns/<?= $campaign['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
                <h2 class="mb-0">Membres — <?= \App\Core\View::e($campaign['name']) ?></h2>
            </div>
            <p class="text-muted mb-0 small">Gestion des accès et des périmètres par membre</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- Formulaire ajout membre -->
        <?php if (\App\Core\Auth::isSuperviseur()): ?>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white pt-3">
                    <h6 class="card-title mb-0"><i class="bi bi-person-plus me-2"></i>Ajouter un membre</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="/campaigns/<?= $campaign['id'] ?>/members">
                        <?= \App\Core\CSRF::field() ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Utilisateur <span class="text-danger">*</span></label>
                            <select name="user_id" class="form-select" required>
                                <option value="">— Sélectionner —</option>
                                <?php foreach ($availableUsers as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= \App\Core\View::e($u['name']) ?> (<?= \App\Core\View::e($u['email']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Rôle dans la campagne</label>
                            <select name="role_in_campaign" class="form-select">
                                <option value="AGENT">Agent</option>
                                <option value="SUPERVISEUR">Superviseur</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Périmètre — Sites (optionnel)</label>
                            <select name="site_ids[]" class="form-select" multiple size="4">
                                <?php foreach ($sites as $site): ?>
                                <option value="<?= $site['id'] ?>"><?= \App\Core\View::e($site['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Laisser les deux champs vides = accès complet.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Périmètre — Locaux (optionnel)</label>
                            <select name="scope_locations[]" class="form-select" multiple size="5">
                                <?php foreach ($locations as $loc): ?>
                                <option value="<?= $loc['id'] ?>">
                                    <?= \App\Core\View::e($loc['code_local']) ?>
                                    <?php if ($loc['designation_local']): ?>— <?= \App\Core\View::e($loc['designation_local']) ?><?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Affectation par local spécifique (prioritaire sur le site).</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-person-plus me-1"></i>Ajouter
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Liste des membres -->
        <div class="col-md-<?= \App\Core\Auth::isSuperviseur() ? '8' : '12' ?>">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white pt-3">
                    <h6 class="card-title mb-0"><i class="bi bi-people me-2"></i>Membres (<?= count($members) ?>)</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Rôle</th>
                                <th>Périmètre</th>
                                <th>Statut</th>
                                <?php if (\App\Core\Auth::isSuperviseur()): ?><th></th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $member): ?>
                            <tr>
                                <td class="fw-semibold"><?= \App\Core\View::e($member['user_name']) ?></td>
                                <td class="small"><?= \App\Core\View::e($member['user_email']) ?></td>
                                <td>
                                    <?php
                                    $roleColors = ['SUPERVISEUR'=>'warning','AGENT'=>'info'];
                                    $roleLabels = ['SUPERVISEUR'=>'Superviseur','AGENT'=>'Agent'];
                                    $role = $member['role_in_campaign'];
                                    ?>
                                    <span class="badge bg-<?= $roleColors[$role] ?? 'secondary' ?>"><?= $roleLabels[$role] ?? $role ?></span>
                                </td>
                                <td class="small text-muted">
                                    <?php
                                    $hasSites     = !empty($member['scope_sites']);
                                    $hasLocations = !empty($member['scope_locations']);
                                    if ($hasSites || $hasLocations):
                                    ?>
                                        <?php if ($hasSites): ?>
                                        <div title="<?= \App\Core\View::e(implode(', ', $member['scope_sites'])) ?>">
                                            <i class="bi bi-building me-1"></i><?= count($member['scope_sites']) ?> site(s)
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($hasLocations): ?>
                                        <div title="<?= \App\Core\View::e(implode(', ', $member['scope_locations'])) ?>">
                                            <i class="bi bi-door-open me-1"></i><?= count($member['scope_locations']) ?> local/locaux
                                        </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                    <span class="text-success"><i class="bi bi-globe me-1"></i>Complet</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $member['is_active'] ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-secondary">Inactif</span>' ?></td>
                                <?php if (\App\Core\Auth::isSuperviseur()): ?>
                                <td class="text-end">
                                    <form method="POST" action="/campaigns/<?= $campaign['id'] ?>/members/<?= $member['id'] ?>/remove" class="d-inline">
                                        <?= \App\Core\CSRF::field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                data-confirm="Retirer <?= \App\Core\View::e($member['user_name']) ?> de la campagne ?">
                                            <i class="bi bi-person-x"></i>
                                        </button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($members)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-people display-6 d-block mb-2 opacity-25"></i>
                                    Aucun membre affecté.
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

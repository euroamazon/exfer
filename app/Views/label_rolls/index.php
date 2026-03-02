<div class="container-fluid py-4">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="/campaigns/<?= $campaign['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
                <h2 class="mb-0">Rouleaux d'étiquettes — <?= \App\Core\View::e($campaign['name']) ?></h2>
            </div>
            <p class="text-muted mb-0 small">Gestion des plages de codes pour les agents</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- Formulaire création rouleau -->
        <?php if (\App\Core\Auth::isSuperviseur()): ?>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white pt-3">
                    <h6 class="card-title mb-0"><i class="bi bi-plus-circle me-2"></i>Créer un rouleau</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger py-2 small">
                        <?php foreach ($errors as $e): ?><div><?= \App\Core\View::e($e) ?></div><?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <form method="POST" action="/campaigns/<?= $campaign['id'] ?>/label-rolls">
                        <?= \App\Core\CSRF::field() ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Agent <span class="text-danger">*</span></label>
                            <select name="agent_id" class="form-select" required>
                                <option value="">— Sélectionner —</option>
                                <?php foreach ($agents as $agent): ?>
                                <option value="<?= $agent['id'] ?>"><?= \App\Core\View::e($agent['name']) ?> (<?= \App\Core\View::e($agent['email']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Code début <span class="text-danger">*</span></label>
                            <input type="text" name="start_code" class="form-control fw-mono"
                                   placeholder="<?= $campaign['config']['code_length'] ?? 6 ?> chiffres" required maxlength="20">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Code fin <span class="text-danger">*</span></label>
                            <input type="text" name="end_code" class="form-control fw-mono"
                                   placeholder="<?= $campaign['config']['code_length'] ?? 6 ?> chiffres" required maxlength="20">
                        </div>
                        <?php
                        $codeLen = $campaign['config']['code_length'] ?? 6;
                        $codeType = $campaign['config']['code_type'] ?? 'NUMERIC';
                        ?>
                        <div class="alert alert-info py-2 small">
                            <i class="bi bi-info-circle me-1"></i>
                            Code <?= $codeType === 'NUMERIC' ? 'numérique' : 'alphanumérique' ?>, <?= $codeLen ?> caractères.
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-plus me-1"></i>Créer
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Liste des rouleaux -->
        <div class="col-md-<?= \App\Core\Auth::isSuperviseur() ? '8' : '12' ?>">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white pt-3">
                    <h6 class="card-title mb-0"><i class="bi bi-upc-scan me-2"></i>Rouleaux (<?= count($rolls) ?>)</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Agent</th>
                                <th>Plage</th>
                                <th>Prochain code</th>
                                <th>Avancement</th>
                                <th>Statut</th>
                                <th>Codes sautés</th>
                                <?php if (\App\Core\Auth::isSuperviseur()): ?><th></th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rolls as $roll): ?>
                            <?php
                            $total = (int)$roll['end_code'] - (int)$roll['start_code'] + 1;
                            $consumed = $total > 0 ? (int)$roll['next_code'] - (int)$roll['start_code'] : 0;
                            $pct = $total > 0 ? min(100, round($consumed / $total * 100)) : 0;
                            $skipped = json_decode($roll['skipped_codes'] ?? '[]', true) ?: [];
                            ?>
                            <tr>
                                <td class="fw-semibold"><?= \App\Core\View::e($roll['agent_name'] ?? '—') ?></td>
                                <td class="fw-mono small">
                                    <?= \App\Core\View::e($roll['start_code']) ?> → <?= \App\Core\View::e($roll['end_code']) ?>
                                </td>
                                <td class="fw-mono"><?= $roll['status'] !== 'CLOSED' ? \App\Core\View::e($roll['next_code']) : '—' ?></td>
                                <td style="min-width:120px;">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height:6px;">
                                            <div class="progress-bar" style="width:<?= $pct ?>%;"></div>
                                        </div>
                                        <small class="text-muted"><?= $pct ?>%</small>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $statusColors = ['OPEN'=>'success','CLOSED'=>'secondary','EXHAUSTED'=>'warning'];
                                    $statusLabels = ['OPEN'=>'Ouvert','CLOSED'=>'Fermé','EXHAUSTED'=>'Épuisé'];
                                    $color = $statusColors[$roll['status']] ?? 'secondary';
                                    $label = $statusLabels[$roll['status']] ?? $roll['status'];
                                    ?>
                                    <span class="badge bg-<?= $color ?>"><?= $label ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($skipped)): ?>
                                    <button class="btn btn-sm btn-outline-warning" data-bs-toggle="tooltip"
                                            title="<?= implode(', ', array_column($skipped, 'code')) ?>">
                                        <?= count($skipped) ?> code(s)
                                    </button>
                                    <?php else: ?>
                                    <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php if (\App\Core\Auth::isSuperviseur()): ?>
                                <td class="text-end">
                                    <?php if ($roll['status'] === 'OPEN'): ?>
                                    <button class="btn btn-sm btn-outline-warning btn-skip-code"
                                            data-id="<?= $roll['id'] ?>" data-code="<?= \App\Core\View::e($roll['next_code']) ?>">
                                        <i class="bi bi-skip-forward me-1"></i>Sauter
                                    </button>
                                    <form method="POST" action="/campaigns/<?= $campaign['id'] ?>/label-rolls/<?= $roll['id'] ?>/close" class="d-inline">
                                        <?= \App\Core\CSRF::field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary"
                                                data-confirm="Clôturer ce rouleau ?">
                                            <i class="bi bi-lock me-1"></i>Clôturer
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($rolls)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="bi bi-upc-scan display-6 d-block mb-2 opacity-25"></i>
                                    Aucun rouleau créé.
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

<!-- Modal sauter code -->
<div class="modal fade" id="skipCodeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sauter un code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="skipCodeForm" method="POST">
                <div class="modal-body">
                    <?= \App\Core\CSRF::field() ?>
                    <p class="mb-3">Code à sauter : <strong id="skipCodeDisplay" class="fw-mono"></strong></p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Raison</label>
                        <select name="reason" class="form-select">
                            <option value="LOST">Perdu</option>
                            <option value="DAMAGED">Endommagé</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning">Sauter ce code</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.btn-skip-code').forEach(btn => {
    btn.addEventListener('click', function () {
        const id = this.dataset.id;
        const code = this.dataset.code;
        document.getElementById('skipCodeDisplay').textContent = code;
        document.getElementById('skipCodeForm').action = '/campaigns/<?= $campaign['id'] ?>/label-rolls/' + id + '/skip';
        new bootstrap.Modal(document.getElementById('skipCodeModal')).show();
    });
});
// Init tooltips
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
</script>

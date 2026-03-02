<div class="container-fluid py-4">
    <!-- En-tête -->
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="/campaigns/<?= $campaign['id'] ?>/import" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
        <h2 class="mb-0">Mapping des colonnes</h2>
    </div>

    <!-- Étapes -->
    <div class="d-flex align-items-center mb-4">
        <span class="badge bg-success rounded-pill px-3 py-2 me-2"><i class="bi bi-check"></i></span>
        <span class="text-muted me-3">Téléverser</span>
        <div class="flex-grow-1 border-top"></div>
        <span class="badge bg-primary rounded-pill px-3 py-2 mx-2">2</span>
        <span class="fw-semibold me-3">Mapping</span>
        <div class="flex-grow-1 border-top"></div>
        <span class="badge bg-light text-muted rounded-pill px-3 py-2 mx-2">3</span>
        <span class="text-muted me-3">Aperçu</span>
        <div class="flex-grow-1 border-top"></div>
        <span class="badge bg-light text-muted rounded-pill px-3 py-2 mx-2">4</span>
        <span class="text-muted">Résultat</span>
    </div>

    <form method="POST" action="/campaigns/<?= $campaign['id'] ?>/import/<?= $job['id'] ?>/preview">
        <?= \App\Core\CSRF::field() ?>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white pt-3">
                <h6 class="card-title mb-0">
                    <i class="bi bi-table me-2"></i>
                    Fichier : <strong><?= \App\Core\View::e($job['filename']) ?></strong>
                    (<?= count($fileHeaders) ?> colonnes détectées)
                </h6>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Associez chaque colonne du fichier à un champ de la campagne. Laissez « — Ignorer —» pour les colonnes à ne pas importer.
                </p>
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Colonne dans le fichier</th>
                                <th>Aperçu (première ligne)</th>
                                <th>Correspondance dans ExFer</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fileHeaders as $i => $header): ?>
                            <tr>
                                <td class="fw-mono fw-semibold"><?= \App\Core\View::e($header) ?></td>
                                <td class="text-muted small"><?= \App\Core\View::e($previewRow[$i] ?? '') ?></td>
                                <td>
                                    <select name="mapping[<?= \App\Core\View::e($header) ?>]" class="form-select form-select-sm">
                                        <option value="">— Ignorer —</option>
                                        <optgroup label="Champs système">
                                            <?php
                                            $systemFields = [
                                                'code_immo' => 'Code immobilisation',
                                                'designation' => 'Désignation',
                                                'code_local' => 'Code local',
                                                'serial_number' => 'N° de série',
                                                'notes' => 'Notes',
                                            ];
                                            foreach ($systemFields as $k => $label):
                                                $selected = $suggestedMapping[$header] === $k ? 'selected' : '';
                                            ?>
                                            <option value="<?= $k ?>" <?= $selected ?>><?= $label ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <?php if (!empty($columns)): ?>
                                        <optgroup label="Colonnes dynamiques">
                                            <?php foreach ($columns as $col): ?>
                                            <option value="col_<?= $col['column_key'] ?>"
                                                    <?= ($suggestedMapping[$header] ?? '') === 'col_' . $col['column_key'] ? 'selected' : '' ?>>
                                                <?= \App\Core\View::e($col['label']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <?php endif; ?>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-eye me-1"></i>Aperçu et validation
            </button>
            <a href="/campaigns/<?= $campaign['id'] ?>/import" class="btn btn-outline-secondary">Annuler</a>
        </div>
    </form>
</div>

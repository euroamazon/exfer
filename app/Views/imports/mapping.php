<?php
// Auto-suggestion : normalise un header pour trouver une correspondance
$normalize = fn(string $s): string => strtolower(trim(preg_replace('/[^a-z0-9]/i', '_', $s)));
$suggested = [];
foreach ($fileHeaders as $header) {
    $norm = $normalize($header);
    // Correspondance exacte ou partielle avec les champs DB
    foreach (array_keys($dbFields) as $key) {
        if ($normalize($key) === $norm || str_contains($norm, $normalize($key))) {
            $suggested[$header] = $key;
            break;
        }
    }
}
$previewRow = $previewRows[0] ?? [];
?>
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
                    Associez chaque colonne du fichier à un champ de la campagne.
                    Laissez <em>— Ignorer —</em> pour les colonnes à ne pas importer.
                </p>
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Colonne dans le fichier</th>
                                <th>Aperçu (1ère ligne)</th>
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
                                        <?php foreach ($dbFields as $key => $label): ?>
                                        <option value="<?= \App\Core\View::e($key) ?>"
                                                <?= ($suggested[$header] ?? '') === $key ? 'selected' : '' ?>>
                                            <?= \App\Core\View::e($label) ?>
                                        </option>
                                        <?php endforeach; ?>
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

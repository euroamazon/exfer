<div class="container-fluid py-4">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="/campaigns/<?= $campaign['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
                <h2 class="mb-0">Importer — <?= \App\Core\View::e($campaign['name']) ?></h2>
            </div>
            <p class="text-muted mb-0 small">Importez vos articles depuis un fichier Excel ou CSV</p>
        </div>
    </div>

    <!-- Étapes -->
    <div class="d-flex align-items-center mb-4">
        <span class="badge bg-primary rounded-pill px-3 py-2 me-2">1</span>
        <span class="fw-semibold me-3">Téléverser</span>
        <div class="flex-grow-1 border-top"></div>
        <span class="badge bg-light text-muted rounded-pill px-3 py-2 mx-2">2</span>
        <span class="text-muted me-3">Mapping</span>
        <div class="flex-grow-1 border-top"></div>
        <span class="badge bg-light text-muted rounded-pill px-3 py-2 mx-2">3</span>
        <span class="text-muted me-3">Aperçu</span>
        <div class="flex-grow-1 border-top"></div>
        <span class="badge bg-light text-muted rounded-pill px-3 py-2 mx-2">4</span>
        <span class="text-muted">Résultat</span>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white pt-3">
                    <h6 class="card-title mb-0"><i class="bi bi-upload me-2"></i>Sélectionner le fichier</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="/campaigns/<?= $campaign['id'] ?>/import/upload" enctype="multipart/form-data">
                        <?= \App\Core\CSRF::field() ?>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Fichier Excel ou CSV <span class="text-danger">*</span></label>
                            <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
                            <div class="form-text">Formats acceptés : .xlsx, .xls, .csv (max 10 Mo)</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Mode d'import</label>
                            <div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="mode" id="modeInsert" value="INSERT" checked>
                                    <label class="form-check-label" for="modeInsert">
                                        <strong>INSERT</strong> — Insérer uniquement les nouveaux articles
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="mode" id="modeUpdate" value="UPDATE">
                                    <label class="form-check-label" for="modeUpdate">
                                        <strong>UPDATE</strong> — Mettre à jour les articles existants
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="mode" id="modeUpsert" value="UPSERT">
                                    <label class="form-check-label" for="modeUpsert">
                                        <strong>UPSERT</strong> — Insérer ou mettre à jour
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="dry_run" id="dryRun" value="1" checked>
                                <label class="form-check-label" for="dryRun">
                                    <strong>Simulation (dry-run)</strong> — Valider sans importer
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-upload me-1"></i>Analyser le fichier
                        </button>
                    </form>
                </div>
            </div>

            <!-- Aide format -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white pt-3">
                    <h6 class="card-title mb-0"><i class="bi bi-question-circle me-2"></i>Format attendu</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2">La première ligne doit contenir les en-têtes de colonnes. Les colonnes suivantes seront mappées lors de l'étape 2 :</p>
                    <ul class="small text-muted mb-2">
                        <li><strong>code_immo</strong> ou <strong>Code</strong> — code de l'immobilisation</li>
                        <li><strong>designation</strong> ou <strong>Désignation</strong> — libellé de l'article</li>
                        <li><strong>code_local</strong> ou <strong>Local</strong> — code du local (créé si inexistant)</li>
                        <li>Toutes les colonnes dynamiques de la campagne</li>
                    </ul>
                    <p class="small text-muted mb-0">CSV : délimiteur point-virgule (;) ou virgule (,), encodage UTF-8.</p>
                </div>
            </div>
        </div>
    </div>
</div>

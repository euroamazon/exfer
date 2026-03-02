<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <!-- En-tête -->
            <div class="d-flex align-items-center gap-2 mb-4">
                <a href="/admin/organizations" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
                <h2 class="mb-0"><?= isset($organization) ? 'Modifier l\'organisation' : 'Nouvelle organisation' ?></h2>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $field => $msgs): foreach ($msgs as $msg): ?>
                    <li><?= \App\Core\View::e($msg) ?></li>
                    <?php endforeach; endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="POST" action="<?= isset($organization) ? "/admin/organizations/{$organization['id']}/update" : '/admin/organizations' ?>">
                        <?= \App\Core\CSRF::field() ?>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nom de l'organisation <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control"
                                   value="<?= \App\Core\View::e($organization['name'] ?? \App\Core\View::old('name')) ?>"
                                   required maxlength="100" placeholder="Mairie de Lyon">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Slug (URL)</label>
                            <input type="text" name="slug" class="form-control fw-mono"
                                   value="<?= \App\Core\View::e($organization['slug'] ?? \App\Core\View::old('slug')) ?>"
                                   maxlength="100" placeholder="mairie-lyon">
                            <div class="form-text">Laissez vide pour générer automatiquement.</div>
                        </div>

                        <div class="mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                                       value="1" <?= ($organization['is_active'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="isActive">Organisation active</label>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i><?= isset($organization) ? 'Enregistrer' : 'Créer' ?>
                            </button>
                            <a href="/admin/organizations" class="btn btn-outline-secondary">Annuler</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <!-- En-tête -->
            <div class="d-flex align-items-center gap-2 mb-4">
                <a href="/campaigns/<?= $campaign['id'] ?>/locations" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h2 class="mb-0">Nouveau local</h2>
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
                    <form method="POST" action="/campaigns/<?= $campaign['id'] ?>/locations">
                        <?= \App\Core\CSRF::field() ?>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Code Local <span class="text-danger">*</span></label>
                            <input type="text" name="code_local" class="form-control fw-mono"
                                   value="<?= \App\Core\View::e(\App\Core\View::old('code_local')) ?>"
                                   placeholder="Ex : B001" required maxlength="50">
                            <div class="form-text">Identifiant unique du local dans la campagne.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Désignation</label>
                            <input type="text" name="designation_local" class="form-control"
                                   value="<?= \App\Core\View::e(\App\Core\View::old('designation_local')) ?>"
                                   placeholder="Ex : Bureau direction" maxlength="255">
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Site</label>
                            <select name="site_id" class="form-select">
                                <option value="">— Aucun site —</option>
                                <?php foreach ($sites as $site): ?>
                                <option value="<?= $site['id'] ?>" <?= \App\Core\View::old('site_id') == $site['id'] ? 'selected' : '' ?>>
                                    <?= \App\Core\View::e($site['name']) ?>
                                    <?php if ($site['code']): ?>(<?= \App\Core\View::e($site['code']) ?>)<?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Créer le local
                            </button>
                            <a href="/campaigns/<?= $campaign['id'] ?>/locations" class="btn btn-outline-secondary">Annuler</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <h2 class="mb-4">Mon profil</h2>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $field => $msgs): foreach ($msgs as $msg): ?>
                    <li><?= \App\Core\View::e($msg) ?></li>
                    <?php endforeach; endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white pt-3">
                    <h6 class="card-title mb-0"><i class="bi bi-person me-2"></i>Informations personnelles</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="/profile/update">
                        <?= \App\Core\CSRF::field() ?>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nom complet</label>
                            <input type="text" name="name" class="form-control"
                                   value="<?= \App\Core\View::e($user['name']) ?>" required maxlength="100">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?= \App\Core\View::e($user['email']) ?>" required maxlength="150">
                        </div>

                        <hr>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nouveau mot de passe</label>
                            <input type="password" name="password" class="form-control" minlength="8"
                                   placeholder="Laisser vide pour ne pas modifier">
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Confirmer le mot de passe</label>
                            <input type="password" name="password_confirm" class="form-control" minlength="8"
                                   placeholder="Confirmer le nouveau mot de passe">
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Enregistrer les modifications
                        </button>
                    </form>
                </div>
            </div>

            <!-- Infos compte -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white pt-3">
                    <h6 class="card-title mb-0"><i class="bi bi-shield me-2"></i>Informations du compte</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Rôle</dt>
                        <dd class="col-sm-7">
                            <?php
                            $roleLabels = ['ADMIN'=>'Administrateur','SUPERVISEUR'=>'Superviseur','AGENT'=>'Agent'];
                            echo $roleLabels[$user['role']] ?? $user['role'];
                            ?>
                        </dd>
                        <dt class="col-sm-5">Dernière connexion</dt>
                        <dd class="col-sm-7 text-muted">
                            <?= $user['last_login_at'] ? \App\Core\View::date($user['last_login_at'], 'd/m/Y H:i') : 'Première connexion' ?>
                        </dd>
                        <dt class="col-sm-5">Membre depuis</dt>
                        <dd class="col-sm-7 text-muted">
                            <?= \App\Core\View::date($user['created_at'], 'd/m/Y') ?>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

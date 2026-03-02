<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <!-- En-tête -->
            <div class="d-flex align-items-center gap-2 mb-4">
                <a href="/users" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
                <h2 class="mb-0"><?= isset($user) ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur' ?></h2>
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
                    <form method="POST" action="<?= isset($user) ? "/users/{$user['id']}/update" : '/users' ?>">
                        <?= \App\Core\CSRF::field() ?>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nom complet <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control"
                                   value="<?= \App\Core\View::e($user['name'] ?? \App\Core\View::old('name')) ?>"
                                   required maxlength="100" placeholder="Prénom Nom">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Adresse email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control"
                                   value="<?= \App\Core\View::e($user['email'] ?? \App\Core\View::old('email')) ?>"
                                   required maxlength="150" placeholder="prenom.nom@exemple.com">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Rôle <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <?php
                                $currentRole = $user['role'] ?? \App\Core\View::old('role', 'AGENT');
                                $roles = ['AGENT' => 'Agent', 'SUPERVISEUR' => 'Superviseur'];
                                if (\App\Core\Auth::isAdmin()) {
                                    $roles['ADMIN'] = 'Administrateur';
                                }
                                foreach ($roles as $val => $label): ?>
                                <option value="<?= $val ?>" <?= $currentRole === $val ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Mot de passe <?= isset($user) ? '' : '<span class="text-danger">*</span>' ?>
                            </label>
                            <input type="password" name="password" class="form-control"
                                   <?= isset($user) ? '' : 'required' ?> minlength="8"
                                   placeholder="<?= isset($user) ? 'Laisser vide pour ne pas modifier' : 'Minimum 8 caractères' ?>">
                        </div>

                        <?php if (!isset($user) || \App\Core\Auth::isAdmin()): ?>
                        <div class="mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                                       value="1" <?= ($user['is_active'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="isActive">Compte actif</label>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i><?= isset($user) ? 'Enregistrer' : 'Créer' ?>
                            </button>
                            <a href="/users" class="btn btn-outline-secondary">Annuler</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container py-4" style="max-width: 800px;">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/campaigns" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
        <h2 class="mb-0"><?= \App\Core\View::e($pageTitle) ?></h2>
    </div>

    <?php $errors = $_SESSION['_flash']['errors'] ?? []; unset($_SESSION['_flash']['errors']); ?>
    <?php if ($errors): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $field => $fieldErrors): foreach ((array)$fieldErrors as $e): ?>
        <div><i class="bi bi-exclamation-circle me-1"></i><?= \App\Core\View::e($e) ?></div>
        <?php endforeach; endforeach; ?>
    </div>
    <?php endif; ?>

    <?php $c = $campaign; $cfg = $config ?? []; ?>
    <form method="POST" action="<?= $c ? '/campaigns/' . $c['id'] . '/update' : '/campaigns' ?>">
        <?= \App\Core\CSRF::field() ?>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-info-circle me-2"></i>Informations générales
            </div>
            <div class="card-body row g-3">
                <div class="col-12">
                    <label class="form-label">Nom de la campagne *</label>
                    <input type="text" name="name" class="form-control" required
                           value="<?= \App\Core\View::e($c['name'] ?? \App\Core\Session::old('name')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Code interne</label>
                    <input type="text" name="code" class="form-control"
                           value="<?= \App\Core\View::e($c['code'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Prestation</label>
                    <select name="service_id" class="form-select">
                        <option value="">— Aucune —</option>
                        <?php foreach ($services as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= ($c['service_id'] ?? 0) == $s['id'] ? 'selected' : '' ?>>
                            <?= \App\Core\View::e($s['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2"><?= \App\Core\View::e($c['description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-key me-2"></i>Configuration des codes d'immobilisation
            </div>
            <div class="card-body row g-3">
                <div class="col-md-3">
                    <label class="form-label">Longueur du code</label>
                    <input type="number" name="code_length" class="form-control" min="4" max="20"
                           value="<?= $cfg['code_length'] ?? 6 ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Type de code</label>
                    <select name="code_type" class="form-select">
                        <option value="NUMERIC"  <?= ($cfg['code_type'] ?? 'NUMERIC') === 'NUMERIC'  ? 'selected' : '' ?>>Numérique</option>
                        <option value="ALPHANUM" <?= ($cfg['code_type'] ?? '') === 'ALPHANUM' ? 'selected' : '' ?>>Alphanumérique</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Code minimum</label>
                    <input type="text" name="min_code" class="form-control" value="<?= $cfg['min_code'] ?? '' ?>" placeholder="000001">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Code maximum</label>
                    <input type="text" name="max_code" class="form-control" value="<?= $cfg['max_code'] ?? '' ?>" placeholder="999999">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Portée de l'unicité</label>
                    <select name="unique_scope" class="form-select">
                        <option value="ORGANIZATION" <?= ($cfg['unique_scope'] ?? 'ORGANIZATION') === 'ORGANIZATION' ? 'selected' : '' ?>>
                            Toute l'organisation
                        </option>
                        <option value="CAMPAIGN" <?= ($cfg['unique_scope'] ?? '') === 'CAMPAIGN' ? 'selected' : '' ?>>
                            Cette campagne uniquement
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-sliders me-2"></i>Options de saisie
            </div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="manual_entry_enabled" value="1"
                               id="manualEntry" <?= !empty($cfg['manual_entry_enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="manualEntry">Saisie manuelle du code autorisée</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mode de saisie manuelle</label>
                    <select name="manual_entry_mode" class="form-select">
                        <option value="FLEX_WITH_ANOMALY" <?= ($cfg['manual_entry_mode'] ?? 'FLEX_WITH_ANOMALY') === 'FLEX_WITH_ANOMALY' ? 'selected' : '' ?>>
                            Flexible (anomalie si hors séquence)
                        </option>
                        <option value="STRICT" <?= ($cfg['manual_entry_mode'] ?? '') === 'STRICT' ? 'selected' : '' ?>>
                            Strict (code exact uniquement)
                        </option>
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="label_roll_enabled" value="1"
                               id="rollEnabled" <?= !empty($cfg['label_roll_enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="rollEnabled">Rouleaux d'étiquettes</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="require_photo" value="1"
                               id="reqPhoto" <?= !empty($cfg['require_photo']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="reqPhoto">Photo obligatoire</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="require_serial" value="1"
                               id="reqSerial" <?= !empty($cfg['require_serial']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="reqSerial">N° Série obligatoire</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-eye me-2"></i>Vision IA (reconnaissance d'image)
            </div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="vision_enabled" value="1"
                               <?= !empty($cfg['vision_enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label">Activer la Vision IA</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Seuil de confiance</label>
                    <input type="number" name="vision_threshold" class="form-control" step="0.05" min="0" max="1"
                           value="<?= $cfg['vision_threshold'] ?? 0.75 ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Mode Vision</label>
                    <select name="vision_mode" class="form-select">
                        <option value="WARNING"  <?= ($cfg['vision_mode'] ?? 'WARNING') === 'WARNING'  ? 'selected' : '' ?>>Avertissement</option>
                        <option value="BLOCKING" <?= ($cfg['vision_mode'] ?? '') === 'BLOCKING' ? 'selected' : '' ?>>Bloquant</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-copy me-2"></i>Duplication d'articles
            </div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="duplicate_copy_photos" value="1"
                               <?= !empty($cfg['duplicate_copy_photos']) ? 'checked' : '' ?>>
                        <label class="form-check-label">Copier les photos lors de la duplication</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-save me-1"></i><?= $c ? 'Enregistrer les modifications' : 'Créer la campagne' ?>
            </button>
            <a href="/campaigns" class="btn btn-outline-secondary btn-lg">Annuler</a>
        </div>
    </form>
</div>

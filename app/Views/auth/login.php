<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion — ExFer</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<style>
    body { background: linear-gradient(135deg, #1a252f 0%, #2c3e50 100%); min-height: 100vh; display:flex; align-items:center; }
    .login-card { background: white; border-radius: 16px; padding: 2.5rem; box-shadow: 0 20px 60px rgba(0,0,0,.3); max-width: 420px; width: 100%; }
    .brand-icon { font-size: 3rem; color: #3498db; }
</style>
</head>
<body>
<div class="container">
    <div class="d-flex justify-content-center">
        <div class="login-card">
            <div class="text-center mb-4">
                <div class="brand-icon"><i class="bi bi-clipboard-data"></i></div>
                <h1 class="h4 mt-2 mb-0">ExFer</h1>
                <p class="text-muted small">Inventaire des Immobilisations</p>
            </div>

            <?php if (isset($_GET['installed'])): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle me-2"></i>Installation réussie ! Connectez-vous pour commencer.
            </div>
            <?php endif; ?>

            <?php
            $flash = $_SESSION['_flash'] ?? [];
            if (!empty($flash['error'])): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($flash['error']) ?>
            </div>
            <?php unset($_SESSION['_flash']['error']); endif; ?>

            <?php if (!empty($flash['success'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($flash['success']) ?>
            </div>
            <?php unset($_SESSION['_flash']['success']); endif; ?>

            <form method="POST" action="/login">
                <?= \App\Core\CSRF::field() ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Adresse email</label>
                    <input type="email" name="email" class="form-control form-control-lg"
                           value="<?= htmlspecialchars($_SESSION['_flash']['_old']['email'] ?? '') ?>"
                           autofocus required placeholder="admin@example.com">
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Mot de passe</label>
                    <input type="password" name="password" class="form-control form-control-lg" required>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Se connecter
                </button>
            </form>

            <p class="text-center text-muted small mt-4 mb-0">
                ExFer v<?= defined('APP_VERSION') ? APP_VERSION : '1.0' ?>
            </p>
        </div>
    </div>
</div>
<?php unset($_SESSION['_flash']); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

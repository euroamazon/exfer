<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= \App\Core\View::e($pageTitle ?? 'ExFer') ?> — ExFer</title>
<?= \App\Core\CSRF::meta() ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="exfer-body">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top exfer-navbar">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="/dashboard">
            <i class="bi bi-clipboard-data me-2"></i>ExFer
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/dashboard') || ($_SERVER['REQUEST_URI'] === '/') ? 'active' : '' ?>" href="/dashboard">
                        <i class="bi bi-speedometer2 me-1"></i>Tableau de bord
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/campaigns') ? 'active' : '' ?>" href="/campaigns">
                        <i class="bi bi-journal-check me-1"></i>Campagnes
                    </a>
                </li>
                <?php if (\App\Core\Auth::isSuperviseur()): ?>
                <li class="nav-item">
                    <a class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/services') ? 'active' : '' ?>" href="/services">
                        <i class="bi bi-briefcase me-1"></i>Prestations
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/users') ? 'active' : '' ?>" href="/users">
                        <i class="bi bi-people me-1"></i>Utilisateurs
                    </a>
                </li>
                <?php endif; ?>
                <?php if (\App\Core\Auth::isAdmin()): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-gear me-1"></i>Admin
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="/admin/organizations"><i class="bi bi-building me-2"></i>Organisations</a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= \App\Core\View::e($_user['name'] ?? 'Inconnu') ?>
                        <span class="badge bg-secondary ms-1 small"><?= \App\Core\Auth::role() ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/profile"><i class="bi bi-person me-2"></i>Mon profil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="/logout" class="d-inline">
                                <?= \App\Core\CSRF::field() ?>
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="bi bi-box-arrow-right me-2"></i>Déconnexion
                                </button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Contenu principal -->
<main class="exfer-main">
    <!-- Messages flash -->
    <?php $flashes = $_user ? \App\Core\Session::getFlashes() : ($__data['_flashes'] ?? []); ?>
    <?php if (!empty($_SESSION['_flash'])): ?>
        <?php foreach ($_SESSION['_flash'] as $type => $msg): ?>
            <?php if (!in_array($type, ['_old', 'errors'])): ?>
            <div class="alert alert-<?= match($type) { 'success'=>'success','error'=>'danger','warning'=>'warning',default=>'info' } ?> alert-dismissible m-3 fade show" role="alert">
                <?= \App\Core\View::e($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php unset($_SESSION['_flash']); ?>
    <?php endif; ?>

    <!-- Vue principale -->
    <?= $content ?? '' ?>
</main>

<!-- Footer -->
<footer class="exfer-footer text-muted text-center py-3 border-top small">
    ExFer v<?= defined('APP_VERSION') ? APP_VERSION : '1.0' ?> — CMS Inventaire Immobilisations
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>

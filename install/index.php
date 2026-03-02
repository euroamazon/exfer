<?php
/**
 * Wizard d'installation ExFer
 * Accessible uniquement si /storage/installed.lock n'existe pas
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH',  ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INSTALL_VERSION', '1.0.0');

// Vérification de l'autoloader
$autoloader = ROOT_PATH . '/vendor/autoload.php';
$hasComposer = file_exists($autoloader);
if ($hasComposer) {
    require_once $autoloader;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start(['cookie_httponly' => true]);
}

$step    = (int)($_GET['step'] ?? 1);
$errors  = [];
$success = [];

// ─── Fonctions utilitaires ─────────────────────────────────────────────────

function install_check_php(): array
{
    $checks = [];

    $checks[] = ['label' => 'PHP version ≥ 7.4', 'ok' => version_compare(PHP_VERSION, '7.4.0', '>='), 'value' => PHP_VERSION];
    $checks[] = ['label' => 'Extension PDO',         'ok' => extension_loaded('pdo'),         'value' => ''];
    $checks[] = ['label' => 'Extension PDO MySQL',   'ok' => extension_loaded('pdo_mysql'),   'value' => ''];
    $checks[] = ['label' => 'Extension JSON',         'ok' => extension_loaded('json'),         'value' => ''];
    $checks[] = ['label' => 'Extension mbstring',     'ok' => extension_loaded('mbstring'),     'value' => ''];
    $checks[] = ['label' => 'Extension fileinfo',     'ok' => extension_loaded('fileinfo'),     'value' => ''];
    $checks[] = ['label' => 'Extension gd',           'ok' => extension_loaded('gd'),           'value' => ''];
    $checks[] = ['label' => 'Extension zip',          'ok' => extension_loaded('zip'),          'value' => '(optionnel)'];
    $checks[] = ['label' => 'Dossier /storage/ accessible en écriture', 'ok' => is_writable(STORAGE_PATH), 'value' => STORAGE_PATH];
    $checks[] = ['label' => 'Dossier /config/ accessible en écriture',  'ok' => is_writable(CONFIG_PATH),  'value' => CONFIG_PATH];
    $checks[] = ['label' => 'Composer (autoloader)',  'ok' => file_exists(ROOT_PATH . '/vendor/autoload.php'), 'value' => ''];

    return $checks;
}

function install_test_db(string $host, string $port, string $dbname, string $user, string $pass): array
{
    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        // Vérifier la version MySQL
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        preg_match('/^(\d+\.\d+)/', $version, $m);
        $major = (float)($m[1] ?? 0);

        if ($major < 5.7) {
            return ['success' => false, 'error' => "MySQL {$version} détecté. Version 5.7+ requise."];
        }

        // Vérifier le support JSON
        $pdo->query("SELECT JSON_OBJECT('test', 1)");

        return ['success' => true, 'pdo' => $pdo, 'version' => $version];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Connexion échouée: ' . $e->getMessage()];
    }
}

function install_run_migrations(PDO $pdo): array
{
    $migDir = ROOT_PATH . '/database/migrations';
    $files  = glob($migDir . '/*.sql');
    sort($files);

    $results = [];

    // Créer la table migrations_log si elle n'existe pas
    $pdo->exec("CREATE TABLE IF NOT EXISTS `migrations_log` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `migration_file` VARCHAR(255) NOT NULL,
        `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_migration_file` (`migration_file`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    foreach ($files as $file) {
        $filename = basename($file);

        // Vérifier si déjà exécutée
        $stmt = $pdo->prepare('SELECT id FROM migrations_log WHERE migration_file = ?');
        $stmt->execute([$filename]);
        if ($stmt->fetch()) {
            $results[] = ['file' => $filename, 'status' => 'skipped'];
            continue;
        }

        try {
            $sql = file_get_contents($file);
            // Exécuter chaque statement séparément
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $stmt_sql) {
                if (!empty($stmt_sql)) {
                    $pdo->exec($stmt_sql);
                }
            }

            $pdo->prepare('INSERT INTO migrations_log (migration_file) VALUES (?)')->execute([$filename]);
            $results[] = ['file' => $filename, 'status' => 'ok'];
        } catch (PDOException $e) {
            $results[] = ['file' => $filename, 'status' => 'error', 'error' => $e->getMessage()];
        }
    }

    return $results;
}

function install_generate_config(array $db, string $appKey): string
{
    return "<?php\n"
        . "// Configuration ExFer — générée automatiquement le " . date('Y-m-d H:i:s') . "\n"
        . "// NE PAS MODIFIER MANUELLEMENT\n\n"
        . "define('DB_HOST', " . var_export($db['host'], true) . ");\n"
        . "define('DB_PORT', " . var_export($db['port'], true) . ");\n"
        . "define('DB_NAME', " . var_export($db['dbname'], true) . ");\n"
        . "define('DB_USER', " . var_export($db['user'], true) . ");\n"
        . "define('DB_PASS', " . var_export($db['pass'], true) . ");\n\n"
        . "define('APP_KEY', " . var_export($appKey, true) . ");\n"
        . "define('APP_NAME', 'ExFer — Inventaire Immobilisations');\n"
        . "define('APP_VERSION', '" . INSTALL_VERSION . "');\n\n"
        . "// Vision IA (optionnel)\n"
        . "define('VISION_MODE_BACKEND', 'stub'); // 'stub' ou 'api'\n"
        . "define('VISION_API_URL', null);\n"
        . "define('VISION_API_KEY', null);\n";
}

// ─── Traitement des étapes ────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postStep = (int)($_POST['step'] ?? 0);
    $step = $postStep; // Rester sur la bonne étape en cas d'erreur

    if ($postStep === 2) {
        // Test connexion DB
        $dbConfig = [
            'host'   => trim($_POST['db_host']   ?? 'localhost'),
            'port'   => trim($_POST['db_port']   ?? '3306'),
            'dbname' => trim($_POST['db_name']   ?? ''),
            'user'   => trim($_POST['db_user']   ?? ''),
            'pass'   => $_POST['db_pass']         ?? '',
        ];

        $result = install_test_db($dbConfig['host'], $dbConfig['port'], $dbConfig['dbname'], $dbConfig['user'], $dbConfig['pass']);

        if ($result['success']) {
            $_SESSION['install_db'] = $dbConfig;
            $_SESSION['install_db_version'] = $result['version'];
            header('Location: /install?step=3');
            exit;
        } else {
            $errors[] = $result['error'];
        }
    }

    elseif ($postStep === 3) {
        // Exécuter les migrations
        if (!isset($_SESSION['install_db'])) {
            header('Location: /install?step=2');
            exit;
        }
        $db  = $_SESSION['install_db'];
        $res = install_test_db($db['host'], $db['port'], $db['dbname'], $db['user'], $db['pass']);
        if (!$res['success']) {
            header('Location: /install?step=2');
            exit;
        }

        $_SESSION['install_migrations'] = install_run_migrations($res['pdo']);
        header('Location: /install?step=4');
        exit;
    }

    elseif ($postStep === 4) {
        // Créer le compte admin
        if (!isset($_SESSION['install_db'])) {
            header('Location: /install?step=2');
            exit;
        }

        $orgName  = trim($_POST['org_name']    ?? '');
        $name     = trim($_POST['admin_name']  ?? '');
        $email    = strtolower(trim($_POST['admin_email'] ?? ''));
        $password = $_POST['admin_password']   ?? '';
        $confirm  = $_POST['admin_confirm']    ?? '';

        if (!$orgName) $errors[] = 'Le nom de l\'organisation est obligatoire.';
        if (!$name)    $errors[] = 'Le nom de l\'administrateur est obligatoire.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Adresse email invalide.';
        if (strlen($password) < 8) $errors[] = 'Le mot de passe doit faire au moins 8 caractères.';
        if ($password !== $confirm) $errors[] = 'Les mots de passe ne correspondent pas.';

        if (empty($errors)) {
            $db  = $_SESSION['install_db'];
            $res = install_test_db($db['host'], $db['port'], $db['dbname'], $db['user'], $db['pass']);

            if ($res['success']) {
                $pdo = $res['pdo'];

                // Créer l'organisation
                $slugBase = preg_replace('/[^a-z0-9]+/', '_', strtolower($orgName));
                $slug = trim($slugBase, '_') ?: 'org';

                $pdo->prepare('INSERT INTO organizations (name, slug, is_active, created_at, updated_at) VALUES (?,?,1,NOW(),NOW())')
                    ->execute([$orgName, $slug]);
                $orgId = $pdo->lastInsertId();

                // Créer l'admin
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare('INSERT INTO users (organization_id, name, email, password_hash, role, is_active, created_at, updated_at) VALUES (?,?,?,?,?,1,NOW(),NOW())')
                    ->execute([$orgId, $name, $email, $hash, 'ADMIN']);

                $_SESSION['install_admin'] = ['org_name' => $orgName, 'email' => $email, 'name' => $name];
                header('Location: /install?step=5');
                exit;
            }
        }
    }

    elseif ($postStep === 5) {
        // Finalisation
        if (!isset($_SESSION['install_db'])) {
            header('Location: /install?step=2');
            exit;
        }

        $db     = $_SESSION['install_db'];
        $appKey = bin2hex(random_bytes(32));

        // Générer config.php
        $configContent = install_generate_config($db, $appKey);
        file_put_contents(CONFIG_PATH . '/config.php', $configContent);

        // Créer installed.lock
        file_put_contents(STORAGE_PATH . '/installed.lock', date('Y-m-d H:i:s') . ' — ExFer v' . INSTALL_VERSION);

        // Nettoyer la session
        session_destroy();

        header('Location: /login?installed=1');
        exit;
    }
}

// ─── Vérification de l'étape 1 ───────────────────────────────────────────────
$phpChecks     = install_check_php();
$hasAllRequired = array_reduce($phpChecks, fn($carry, $c) => $carry && ($c['ok'] || str_contains($c['value'], '(optionnel)')), true);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Installation ExFer — Étape <?= $step ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<style>
    body { background: #f0f4f8; }
    .install-wrapper { max-width: 750px; margin: 40px auto; }
    .install-header { background: linear-gradient(135deg, #2c3e50, #3498db); color: white; border-radius: 12px 12px 0 0; padding: 2rem; }
    .install-body { background: white; border-radius: 0 0 12px 12px; padding: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,.1); }
    .step-badge { background: rgba(255,255,255,.2); border-radius: 20px; padding: .3rem .8rem; font-size:.85rem; }
    .check-ok   { color: #27ae60; }
    .check-fail { color: #e74c3c; }
    .progress-steps { display: flex; gap: .5rem; margin-bottom: 2rem; }
    .progress-steps .step { flex:1; padding:.5rem; text-align:center; border-radius:6px; font-size:.8rem; font-weight:600; }
    .progress-steps .step.active  { background:#3498db; color:white; }
    .progress-steps .step.done    { background:#27ae60; color:white; }
    .progress-steps .step.pending { background:#ecf0f1; color:#7f8c8d; }
</style>
</head>
<body>
<div class="install-wrapper">
    <div class="install-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-1">⚙️ Installation ExFer</h1>
                <p class="mb-0 opacity-75">CMS Inventaire Immobilisations v<?= INSTALL_VERSION ?></p>
            </div>
            <span class="step-badge">Étape <?= $step ?> / 5</span>
        </div>
    </div>

    <div class="install-body">
        <!-- Barre de progression -->
        <div class="progress-steps">
            <?php
            $stepLabels = ['Vérifications', 'Base de données', 'Migrations', 'Administrateur', 'Finalisation'];
            for ($i = 1; $i <= 5; $i++):
                $cls = $i < $step ? 'done' : ($i === $step ? 'active' : 'pending');
            ?>
            <div class="step <?= $cls ?>">
                <?= $i < $step ? '✓ ' : '' ?><?= $stepLabels[$i-1] ?>
            </div>
            <?php endfor; ?>
        </div>

        <!-- Messages d'erreur -->
        <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- ── ÉTAPE 1 : Vérifications système ── -->
        <?php if ($step === 1): ?>
        <h4 class="mb-3"><i class="bi bi-search me-2"></i>Vérifications système</h4>
        <table class="table">
            <tbody>
            <?php foreach ($phpChecks as $check): ?>
            <tr>
                <td>
                    <i class="bi <?= $check['ok'] ? 'bi-check-circle-fill check-ok' : 'bi-x-circle-fill check-fail' ?>"></i>
                    <?= htmlspecialchars($check['label']) ?>
                </td>
                <td class="text-muted small"><?= htmlspecialchars($check['value']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (!$hasComposer): ?>
        <div class="alert alert-warning">
            <strong>Composer non installé.</strong> Exécutez <code>composer install</code> depuis la racine du projet avant de continuer.
        </div>
        <?php endif; ?>

        <?php if ($hasAllRequired && $hasComposer): ?>
        <a href="/install?step=2" class="btn btn-primary btn-lg">Continuer <i class="bi bi-arrow-right"></i></a>
        <?php else: ?>
        <button class="btn btn-secondary btn-lg" disabled>Corriger les erreurs d'abord</button>
        <a href="/install?step=1" class="btn btn-outline-secondary ms-2">Recharger</a>
        <?php endif; ?>

        <!-- ── ÉTAPE 2 : Base de données ── -->
        <?php elseif ($step === 2): ?>
        <h4 class="mb-3"><i class="bi bi-database me-2"></i>Configuration de la base de données</h4>
        <form method="POST" action="/install">
            <input type="hidden" name="step" value="2">
            <div class="row g-3">
                <div class="col-8">
                    <label class="form-label">Hôte MySQL *</label>
                    <input type="text" name="db_host" class="form-control" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
                </div>
                <div class="col-4">
                    <label class="form-label">Port</label>
                    <input type="number" name="db_port" class="form-control" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Nom de la base de données *</label>
                    <input type="text" name="db_name" class="form-control" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" placeholder="exfer_db" required>
                </div>
                <div class="col-6">
                    <label class="form-label">Utilisateur MySQL *</label>
                    <input type="text" name="db_user" class="form-control" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required>
                </div>
                <div class="col-6">
                    <label class="form-label">Mot de passe MySQL</label>
                    <input type="password" name="db_pass" class="form-control">
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary btn-lg">Tester la connexion <i class="bi bi-arrow-right"></i></button>
            </div>
        </form>

        <!-- ── ÉTAPE 3 : Migrations ── -->
        <?php elseif ($step === 3): ?>
        <h4 class="mb-3"><i class="bi bi-database-gear me-2"></i>Création des tables</h4>
        <p class="text-muted">Cliquez sur le bouton ci-dessous pour créer la structure de la base de données.</p>
        <form method="POST" action="/install">
            <input type="hidden" name="step" value="3">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-play-circle me-1"></i>Exécuter les migrations
            </button>
        </form>

        <?php if (!empty($_SESSION['install_migrations'])): ?>
        <div class="mt-3">
            <?php foreach ($_SESSION['install_migrations'] as $m): ?>
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi <?= $m['status'] === 'ok' ? 'bi-check-circle text-success' : ($m['status'] === 'skipped' ? 'bi-skip-forward text-muted' : 'bi-x-circle text-danger') ?>"></i>
                <code class="small"><?= htmlspecialchars($m['file']) ?></code>
                <?php if (!empty($m['error'])): ?>
                <span class="text-danger small"><?= htmlspecialchars($m['error']) ?></span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ── ÉTAPE 4 : Compte admin ── -->
        <?php elseif ($step === 4): ?>
        <h4 class="mb-3"><i class="bi bi-person-gear me-2"></i>Création du compte administrateur</h4>
        <form method="POST" action="/install">
            <input type="hidden" name="step" value="4">
            <div class="mb-3">
                <label class="form-label">Nom de l'organisation *</label>
                <input type="text" name="org_name" class="form-control" value="<?= htmlspecialchars($_POST['org_name'] ?? '') ?>" placeholder="Mon Organisation" required>
            </div>
            <hr>
            <div class="mb-3">
                <label class="form-label">Nom de l'administrateur *</label>
                <input type="text" name="admin_name" class="form-control" value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email *</label>
                <input type="email" name="admin_email" class="form-control" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required>
            </div>
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label">Mot de passe * (min. 8 caractères)</label>
                    <input type="password" name="admin_password" class="form-control" required minlength="8">
                </div>
                <div class="col-6">
                    <label class="form-label">Confirmer le mot de passe *</label>
                    <input type="password" name="admin_confirm" class="form-control" required>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary btn-lg">Créer le compte <i class="bi bi-arrow-right"></i></button>
            </div>
        </form>

        <!-- ── ÉTAPE 5 : Finalisation ── -->
        <?php elseif ($step === 5): ?>
        <div class="text-center py-3">
            <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
            <h4 class="mt-3">Presque terminé !</h4>
            <p class="text-muted">Cliquez sur le bouton pour finaliser l'installation.</p>
            <?php if (!empty($_SESSION['install_admin'])): ?>
            <div class="alert alert-info text-start">
                <strong>Compte créé :</strong><br>
                Organisation : <?= htmlspecialchars($_SESSION['install_admin']['org_name']) ?><br>
                Admin : <?= htmlspecialchars($_SESSION['install_admin']['name']) ?> (<?= htmlspecialchars($_SESSION['install_admin']['email']) ?>)
            </div>
            <?php endif; ?>
            <form method="POST" action="/install">
                <input type="hidden" name="step" value="5">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="bi bi-rocket-takeoff me-1"></i>Finaliser l'installation
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <div class="text-center mt-3 text-muted small">
        ExFer v<?= INSTALL_VERSION ?> — CMS Inventaire Immobilisations
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

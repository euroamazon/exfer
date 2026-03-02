<?php
/**
 * ExFer — CMS Inventaire Immobilisations
 * Point d'entrée unique (Front Controller)
 */

declare(strict_types=1);

// Définition des constantes de base
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH',  ROOT_PATH . '/app');
define('PUBLIC_PATH', __DIR__);
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('CONFIG_PATH', ROOT_PATH . '/config');

// Chargement de l'autoloader Composer
$autoloader = ROOT_PATH . '/vendor/autoload.php';
if (!file_exists($autoloader)) {
    http_response_code(503);
    die('<h1>Erreur : les dépendances ne sont pas installées.</h1><p>Veuillez exécuter <code>composer install</code> puis accéder à <a href="/install">/install</a>.</p>');
}
require_once $autoloader;

// Chargement de la configuration (si elle existe)
$configFile = CONFIG_PATH . '/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

// Démarrage de la session
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true,
]);

// Vérification de l'installation
$installedLock = STORAGE_PATH . '/installed.lock';

// Gestion de la route /install
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/') ?: '/';

// Si pas encore installé ou route /install
if (!file_exists($installedLock)) {
    // Rediriger vers le wizard d'installation (sauf si on est déjà sur /install)
    if (!str_starts_with($uri, '/install')) {
        header('Location: /install');
        exit;
    }
}

// Si installé et on accède à /install → redirection
if (file_exists($installedLock) && str_starts_with($uri, '/install')) {
    header('Location: /');
    exit;
}

// Si pas encore installé : gérer /install
if (!file_exists($installedLock)) {
    require_once ROOT_PATH . '/install/index.php';
    exit;
}

// Application installée : utiliser le routeur MVC
use App\Core\Router;
use App\Core\Request;

$request = new Request();
$router  = new Router();

// Chargement des routes
require_once CONFIG_PATH . '/routes.php';

// Dispatch
$router->dispatch($request);

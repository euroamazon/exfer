# ExFer — CMS Inventaire des Immobilisations

Application web professionnelle de gestion d'inventaire des immobilisations, auto-installable, multi-tenant, destinée aux missions terrain.

## Fonctionnalités

- **Multi-tenant** : isolation complète par organisation
- **Rôles** : Administrateur, Superviseur, Agent (global et par campagne)
- **Campagnes d'inventaire** avec configuration complète (codes, rouleaux, vision IA)
- **Hiérarchie** : Services → Campagnes → Sites → Locaux → Articles
- **Saisie tableur** : édition inline, autosave, copier/coller
- **Colonnes dynamiques** : champs personnalisés par campagne (texte, nombre, date, liste, image…)
- **Rouleaux d'étiquettes** : attribution séquentielle atomique de codes par agent
- **Contrôle qualité** : détection de doublons, codes hors plage, séquences manquantes
- **Anomalies** : workflow OPEN → INVESTIGATION → RESOLVED/REJECTED
- **Vision IA** : vérification image/désignation (stub + API externe configurable)
- **Import Excel/CSV** : modes INSERT/UPDATE/UPSERT avec dry-run
- **Exports** : CSV, XLSX (PhpSpreadsheet), PDF (Dompdf)
- **Wizard d'installation** en 5 étapes via `/install`

## Prérequis

- PHP 8.0+ avec extensions : `pdo`, `pdo_mysql`, `json`, `mbstring`, `fileinfo`, `zip`, `gd`
- MySQL 5.7+ (support JSON requis)
- Composer
- Serveur web Apache avec `mod_rewrite` activé (ou Nginx avec configuration équivalente)

## Installation

### 1. Cloner / déployer les fichiers

```bash
git clone <repo> /var/www/exfer
cd /var/www/exfer
composer install --no-dev --optimize-autoloader
```

### 2. Permissions

```bash
chmod -R 755 /var/www/exfer
chmod -R 775 /var/www/exfer/storage
chown -R www-data:www-data /var/www/exfer/storage
```

### 3. Configuration Apache

```apache
<VirtualHost *:80>
    ServerName exfer.exemple.com
    DocumentRoot /var/www/exfer/public

    <Directory /var/www/exfer/public>
        AllowOverride All
        Require all granted
    </Directory>

    # Bloquer l'accès au reste de l'application
    <Directory /var/www/exfer>
        Require all denied
    </Directory>
    <Directory /var/www/exfer/public>
        Require all granted
    </Directory>
</VirtualHost>
```

### 4. Wizard d'installation

Accédez à `http://votre-domaine/install` et suivez les 5 étapes :

1. **Vérifications système** — extensions PHP, droits d'écriture
2. **Base de données** — connexion MySQL, création des tables
3. **Migrations** — exécution automatique des migrations SQL
4. **Administrateur** — création de l'organisation et du compte admin
5. **Finalisation** — génération de la configuration, verrouillage du wizard

Après installation, `/install` est automatiquement bloqué.

## Structure des fichiers

```
/public/           ← Racine web (DocumentRoot)
  index.php        ← Front controller
  .htaccess        ← Réécriture URL
  assets/          ← CSS, JS

/app/
  Core/            ← MVC : Router, Request, Response, Database, Auth, CSRF, Session, View
  Controllers/     ← Contrôleurs
  Services/        ← Logique métier
  Middleware/      ← Auth, CSRF, Scope
  Views/           ← Templates PHP

/config/
  routes.php       ← Table des routes
  config.php       ← Généré par le wizard (constantes BDD + app)

/database/
  migrations/      ← Fichiers SQL ordonnés (001_*.sql … 014_*.sql)

/storage/
  uploads/         ← Photos des immobilisations (hors webroot)
  logs/            ← Journaux applicatifs
  cache/           ← Cache temporaire
  installed.lock   ← Créé après installation réussie

/install/          ← Wizard d'installation (bloqué après setup)
/vendor/           ← Dépendances Composer
```

## Architecture

### Rôles et permissions

| Rôle | Accès |
|------|-------|
| **ADMIN** | Toutes les organisations, tous les utilisateurs, toutes les campagnes |
| **SUPERVISEUR** | Campagnes de son organisation (membre), gestion des locaux et anomalies |
| **AGENT** | Locaux assignés (scope), saisie des articles |

### Workflow d'une campagne

```
DRAFT → ACTIVE → CLOSED
```

- **DRAFT** : configuration, ajout de membres, création de colonnes
- **ACTIVE** : saisie terrain, import, gestion des anomalies
- **CLOSED** : lecture seule, exports finaux

### Workflow d'un local

```
DRAFT → IN_PROGRESS → VALIDATED
                    ↘ NEEDS_REVIEW
```

La validation d'un local est bloquée si des anomalies de sévérité `BLOCKING` sont ouvertes.

### Codes immobilisations

Chaque campagne définit :
- **Longueur** : ex. 6 caractères
- **Type** : NUMERIC (000001) ou ALPHANUM (A00001)
- **Plage** : code minimum et maximum
- **Périmètre d'unicité** : par campagne ou par organisation

Les **rouleaux d'étiquettes** permettent d'attribuer des plages de codes aux agents. L'attribution est atomique (SELECT FOR UPDATE) pour éviter les doublons en environnement concurrent.

## Sécurité

- **CSRF** : token dans session, vérifié sur tous les POST/PUT/DELETE
- **XSS** : `htmlspecialchars()` systématique via `View::e()`
- **Injection SQL** : PDO prepared statements exclusivement
- **Auth** : `password_hash()` BCRYPT (coût 12) + `password_verify()`
- **Sessions** : régénération d'ID au login, durée configurable
- **Rate limiting** : 5 tentatives / 5 min par IP, blocage 15 min
- **Upload** : validation MIME type + extension, stockage hors webroot
- **Scope** : vérification du périmètre sur chaque route de campagne
- **Headers** : X-Frame-Options, X-Content-Type-Options via `.htaccess`

## Développement

### Dépendances

```bash
composer install
```

### Migrations

Les migrations s'exécutent automatiquement via le wizard. Pour les relancer manuellement :

```php
// Depuis install/index.php ou un script dédié
$files = glob(ROOT_PATH . '/database/migrations/*.sql');
sort($files);
foreach ($files as $file) {
    // ... exécuter chaque fichier non encore appliqué
}
```

### Variables d'environnement (config.php généré)

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'exfer');
define('DB_USER', 'exfer_user');
define('DB_PASS', 'secret');
define('APP_KEY', '...'); // clé aléatoire 32 octets
define('APP_ENV', 'production');
```

## Dépendances Composer

| Package | Usage |
|---------|-------|
| `phpoffice/phpspreadsheet` | Import/Export Excel (XLSX) |
| `dompdf/dompdf` | Génération PDF (PV inventaire) |
| `vlucas/phpdotenv` | Variables d'environnement (optionnel) |

## Licence

Usage interne — Tous droits réservés.

-- Migration 002: Organisations (multi-tenant)
CREATE TABLE IF NOT EXISTS `organizations` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(255) NOT NULL,
    `slug`          VARCHAR(100) NOT NULL,
    `logo_path`     VARCHAR(500)          DEFAULT NULL,
    `settings_json` JSON                  DEFAULT NULL COMMENT 'Paramètres organisation',
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_org_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration 010: Colonnes dynamiques
CREATE TABLE IF NOT EXISTS `dynamic_columns` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `organization_id` INT UNSIGNED NOT NULL,
    `campaign_id`     INT UNSIGNED NOT NULL,
    `column_key`      VARCHAR(64)  NOT NULL COMMENT 'Clé slug stable, utilisée dans attributes JSON',
    `label`           VARCHAR(255) NOT NULL COMMENT 'Libellé affiché',
    `type`            ENUM('text','number','date','select','multi_select','boolean','image','images','file') NOT NULL DEFAULT 'text',
    `options_json`    JSON         DEFAULT NULL COMMENT 'Pour select/multi_select: [{value, label}]',
    `validation_json` JSON         DEFAULT NULL COMMENT '{required, regex, min, max}',
    `position`        SMALLINT     NOT NULL DEFAULT 0,
    `is_system`       TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Colonnes système non supprimables',
    `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_col_key` (`campaign_id`, `column_key`),
    KEY `idx_col_org`      (`organization_id`),
    KEY `idx_col_campaign` (`campaign_id`),
    CONSTRAINT `fk_col_org`      FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_col_campaign` FOREIGN KEY (`campaign_id`)     REFERENCES `campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

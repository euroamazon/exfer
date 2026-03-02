-- Migration 001: Table de suivi des migrations
CREATE TABLE IF NOT EXISTS `migrations_log` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `migration_file` VARCHAR(255) NOT NULL,
    `executed_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_migration_file` (`migration_file`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration 006: Campagnes d'inventaire
CREATE TABLE IF NOT EXISTS `campaigns` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `organization_id` INT UNSIGNED NOT NULL,
    `service_id`      INT UNSIGNED          DEFAULT NULL,
    `name`            VARCHAR(255) NOT NULL,
    `code`            VARCHAR(50)           DEFAULT NULL,
    `description`     TEXT                  DEFAULT NULL,
    `status`          ENUM('DRAFT','ACTIVE','CLOSED') NOT NULL DEFAULT 'DRAFT',
    `config`          JSON                  DEFAULT NULL COMMENT 'code_length, code_type, min_code, max_code, unique_scope, manual_entry_enabled, manual_entry_mode, label_roll_enabled, vision_enabled, vision_threshold, vision_mode, require_photo, require_serial, duplicate_copy_photos, duplicate_reset_fields',
    `started_at`      DATETIME              DEFAULT NULL,
    `closed_at`       DATETIME              DEFAULT NULL,
    `created_by`      INT UNSIGNED          DEFAULT NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_campaign_org`    (`organization_id`),
    KEY `idx_campaign_service` (`service_id`),
    KEY `idx_campaign_status`  (`status`),
    CONSTRAINT `fk_campaign_org`     FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_campaign_service` FOREIGN KEY (`service_id`)      REFERENCES `services` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_campaign_creator` FOREIGN KEY (`created_by`)      REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

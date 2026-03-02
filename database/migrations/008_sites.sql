-- Migration 008: Sites
CREATE TABLE IF NOT EXISTS `sites` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `organization_id` INT UNSIGNED NOT NULL,
    `campaign_id`     INT UNSIGNED NOT NULL,
    `name`            VARCHAR(255) NOT NULL,
    `code`            VARCHAR(50)           DEFAULT NULL,
    `address`         TEXT                  DEFAULT NULL,
    `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_site_org`      (`organization_id`),
    KEY `idx_site_campaign` (`campaign_id`),
    CONSTRAINT `fk_site_org`      FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_site_campaign` FOREIGN KEY (`campaign_id`)     REFERENCES `campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

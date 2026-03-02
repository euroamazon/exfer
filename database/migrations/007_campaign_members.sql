-- Migration 007: Membres de campagne + scope
CREATE TABLE IF NOT EXISTS `campaign_members` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `organization_id`   INT UNSIGNED NOT NULL,
    `campaign_id`       INT UNSIGNED NOT NULL,
    `user_id`           INT UNSIGNED NOT NULL,
    `role_in_campaign`  ENUM('SUPERVISEUR','AGENT') NOT NULL DEFAULT 'AGENT',
    `is_active`         TINYINT(1)   NOT NULL DEFAULT 1,
    `assigned_by`       INT UNSIGNED          DEFAULT NULL,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_member` (`campaign_id`, `user_id`),
    KEY `idx_member_org`  (`organization_id`),
    KEY `idx_member_user` (`user_id`),
    CONSTRAINT `fk_cm_org`      FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cm_campaign` FOREIGN KEY (`campaign_id`)     REFERENCES `campaigns` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cm_user`     FOREIGN KEY (`user_id`)         REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scope des membres (visibilité par site/local)
-- Si aucune ligne pour un membre → accès complet à la campagne
CREATE TABLE IF NOT EXISTS `campaign_member_scope` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `member_id`        INT UNSIGNED NOT NULL COMMENT 'FK campaign_members.id',
    `site_id`          INT UNSIGNED          DEFAULT NULL COMMENT 'NULL = tous les sites',
    `location_id`      INT UNSIGNED          DEFAULT NULL COMMENT 'NULL = tous les locaux du site',
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_scope_member`   (`member_id`),
    KEY `idx_scope_site`     (`site_id`),
    KEY `idx_scope_location` (`location_id`),
    CONSTRAINT `fk_scope_member` FOREIGN KEY (`member_id`) REFERENCES `campaign_members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

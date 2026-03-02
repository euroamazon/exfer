-- Migration 004: Journal d'audit
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `organization_id` INT UNSIGNED             DEFAULT NULL,
    `user_id`         INT UNSIGNED             DEFAULT NULL,
    `action`          VARCHAR(100)    NOT NULL COMMENT 'Ex: LOGIN, CREATE_CAMPAIGN, VALIDATE_LOCATION...',
    `entity_type`     VARCHAR(100)             DEFAULT NULL COMMENT 'Ex: campaign, location, inventory_item',
    `entity_id`       INT UNSIGNED             DEFAULT NULL,
    `old_values`      JSON                     DEFAULT NULL,
    `new_values`      JSON                     DEFAULT NULL,
    `ip_address`      VARCHAR(45)              DEFAULT NULL,
    `user_agent`      VARCHAR(500)             DEFAULT NULL,
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_org`    (`organization_id`),
    KEY `idx_audit_user`   (`user_id`),
    KEY `idx_audit_entity` (`entity_type`, `entity_id`),
    KEY `idx_audit_action` (`action`),
    KEY `idx_audit_date`   (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

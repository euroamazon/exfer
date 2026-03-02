-- Migration 011: Rouleaux d'étiquettes
CREATE TABLE IF NOT EXISTS `label_rolls` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `organization_id` INT UNSIGNED NOT NULL,
    `campaign_id`     INT UNSIGNED NOT NULL,
    `agent_id`        INT UNSIGNED NOT NULL,
    `start_code`      VARCHAR(50)  NOT NULL,
    `end_code`        VARCHAR(50)  NOT NULL,
    `next_code`       VARCHAR(50)  NOT NULL,
    `status`          ENUM('OPEN','CLOSED','EXHAUSTED') NOT NULL DEFAULT 'OPEN',
    `skipped_codes`   JSON         DEFAULT NULL COMMENT '[{code, reason, skipped_at}]',
    `closed_at`       DATETIME     DEFAULT NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_roll_campaign` (`campaign_id`),
    KEY `idx_roll_agent`    (`agent_id`),
    KEY `idx_roll_status`   (`status`),
    CONSTRAINT `fk_roll_org`      FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_roll_campaign` FOREIGN KEY (`campaign_id`)     REFERENCES `campaigns` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_roll_agent`    FOREIGN KEY (`agent_id`)        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

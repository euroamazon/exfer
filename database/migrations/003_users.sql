-- Migration 003: Utilisateurs
CREATE TABLE IF NOT EXISTS `users` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `organization_id` INT UNSIGNED NOT NULL,
    `name`            VARCHAR(255) NOT NULL,
    `email`           VARCHAR(255) NOT NULL,
    `password_hash`   VARCHAR(255) NOT NULL,
    `role`            ENUM('ADMIN','SUPERVISEUR','AGENT') NOT NULL DEFAULT 'AGENT',
    `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
    `last_login_at`   DATETIME              DEFAULT NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_email` (`email`),
    KEY `idx_user_org` (`organization_id`),
    CONSTRAINT `fk_users_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des tentatives de connexion (rate limiting)
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip_address`    VARCHAR(45)  NOT NULL,
    `email`         VARCHAR(255)          DEFAULT NULL,
    `attempt_count` INT          NOT NULL DEFAULT 1,
    `window_start`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `blocked_until` DATETIME              DEFAULT NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_login_ip` (`ip_address`),
    KEY `idx_login_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table de réinitialisation de mot de passe
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(255) NOT NULL,
    `expires_at` DATETIME     NOT NULL,
    `used_at`    DATETIME              DEFAULT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pr_token` (`token_hash`),
    CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

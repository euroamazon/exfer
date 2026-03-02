-- Migration 015: Rendre site_id nullable dans locations
-- La création d'un local sans site est un cas d'usage valide (premier local sans sites créés)

ALTER TABLE `locations`
    DROP FOREIGN KEY `fk_loc_site`,
    MODIFY COLUMN `site_id` INT UNSIGNED DEFAULT NULL,
    ADD CONSTRAINT `fk_loc_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL;

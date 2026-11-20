-- Contact form and reCAPTCHA switches.
-- Import once after deploy/upgrade-public-site.sql.
-- Importing again does not add the columns a second time and does not change
-- a later choice. A missing contact-form value stays on. reCAPTCHA stays off.

SET @contact_form := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'public_site_controls'
    AND COLUMN_NAME = 'contact_form_enabled'
);
SET @sql := IF(@contact_form = 0,
  'ALTER TABLE `public_site_controls` ADD COLUMN `contact_form_enabled` tinyint(1) NOT NULL DEFAULT 1 AFTER `maintenance_enabled`',
  'SELECT 1');
PREPARE public_site_contact_form FROM @sql;
EXECUTE public_site_contact_form;
DEALLOCATE PREPARE public_site_contact_form;

SET @recaptcha := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'public_site_controls'
    AND COLUMN_NAME = 'recaptcha_enabled'
);
SET @sql := IF(@recaptcha = 0,
  'ALTER TABLE `public_site_controls` ADD COLUMN `recaptcha_enabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `contact_form_enabled`',
  'SELECT 1');
PREPARE public_site_recaptcha FROM @sql;
EXECUTE public_site_recaptcha;
DEALLOCATE PREPARE public_site_recaptcha;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_20_130001_add_public_site_contact_controls', COALESCE((SELECT MAX(`batch`) FROM (SELECT `batch` FROM `migrations`) AS `migration_batch`), 0) + 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS `existing_migrations` WHERE `existing_migrations`.`migration` = '2026_11_20_130001_add_public_site_contact_controls');

-- PDF address display and party post office.
-- Import once on a database that already has company_addresses and party_addresses.
-- Importing again does not add a second column, does not duplicate the migration row,
-- and does not turn a cleared Show on PDFs flag back on.

SET @show_on_pdf := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'company_addresses'
    AND COLUMN_NAME = 'show_on_pdf'
);
SET @sql := IF(@show_on_pdf = 0,
  'ALTER TABLE `company_addresses` ADD COLUMN `show_on_pdf` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_active`',
  'SELECT 1');
PREPARE pdf_show_column FROM @sql;
EXECUTE pdf_show_column;
DEALLOCATE PREPARE pdf_show_column;

SET @sql := IF(@show_on_pdf = 0,
  'UPDATE `company_addresses` SET `show_on_pdf` = 1 WHERE `is_active` = 1 AND `address_type` IN (''registered_office'', ''corporate_office'')',
  'SELECT 1');
PREPARE pdf_show_defaults FROM @sql;
EXECUTE pdf_show_defaults;
DEALLOCATE PREPARE pdf_show_defaults;

SET @post_office := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'party_addresses'
    AND COLUMN_NAME = 'post_office'
);
SET @sql := IF(@post_office = 0,
  'ALTER TABLE `party_addresses` ADD COLUMN `post_office` varchar(120) DEFAULT NULL AFTER `postcode`',
  'SELECT 1');
PREPARE party_post_office FROM @sql;
EXECUTE party_post_office;
DEALLOCATE PREPARE party_post_office;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_20_100001_add_pdf_address_columns', COALESCE((SELECT MAX(`batch`) FROM (SELECT `batch` FROM `migrations`) AS `migration_batch`), 0) + 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS `existing_migrations` WHERE `existing_migrations`.`migration` = '2026_11_20_100001_add_pdf_address_columns');

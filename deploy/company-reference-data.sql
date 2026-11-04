-- Ahmad Solution Ltd. ERP document categories
-- No company profile, certificates, or user records.
-- Import once, after deploy/reference-data.sql, on a clean database.
-- If these primary keys already exist, stop and review. Do not force the import.

SET NAMES utf8mb4;

INSERT INTO `document_categories` (`id`, `name`, `slug`, `is_system`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Certificate of Incorporation', 'certificate-of-incorporation', 1, 1, 1, NOW(), NOW()),
(2, 'Memorandum of Association', 'memorandum-of-association', 1, 1, 2, NOW(), NOW()),
(3, 'Articles of Association', 'articles-of-association', 1, 1, 3, NOW(), NOW()),
(4, 'Trade Licence', 'trade-licence', 1, 1, 4, NOW(), NOW()),
(5, 'TIN Certificate', 'tin-certificate', 1, 1, 5, NOW(), NOW()),
(6, 'BIN/VAT Registration Certificate', 'bin-vat-registration-certificate', 1, 1, 6, NOW(), NOW()),
(7, 'Bank Solvency Certificate', 'bank-solvency-certificate', 1, 1, 7, NOW(), NOW()),
(8, 'Bank Account Documents', 'bank-account-documents', 1, 1, 8, NOW(), NOW()),
(9, 'Board Resolution', 'board-resolution', 1, 1, 9, NOW(), NOW()),
(10, 'RJSC Documents', 'rjsc-documents', 1, 1, 10, NOW(), NOW()),
(11, 'Shareholding Documents', 'shareholding-documents', 1, 1, 11, NOW(), NOW()),
(12, 'ISO Certificates', 'iso-certificates', 1, 1, 12, NOW(), NOW()),
(13, 'Electrical Contractor Licence', 'electrical-contractor-licence', 1, 1, 13, NOW(), NOW()),
(14, 'Manufacturer Authorization', 'manufacturer-authorization', 1, 1, 14, NOW(), NOW()),
(15, 'Distributor Authorization', 'distributor-authorization', 1, 1, 15, NOW(), NOW()),
(16, 'Experience Certificate', 'experience-certificate', 1, 1, 16, NOW(), NOW()),
(17, 'Project Completion Certificate', 'project-completion-certificate', 1, 1, 17, NOW(), NOW()),
(18, 'Tax Clearance Certificate', 'tax-clearance-certificate', 1, 1, 18, NOW(), NOW()),
(19, 'Insurance Certificate', 'insurance-certificate', 1, 1, 19, NOW(), NOW()),
(20, 'Custom Documents', 'custom-documents', 1, 1, 20, NOW(), NOW());

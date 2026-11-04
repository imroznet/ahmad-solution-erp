<?php

namespace App\Company;

/**
 * System document categories. Administrators may add more.
 * Names describe the record. They are not a claim that a file meets a statute.
 */
final class DocumentCategoryCatalog
{
    /**
     * @return list<array{id: int, name: string, slug: string, sort_order: int}>
     */
    public static function categories(): array
    {
        return [
            self::category(1, 'Certificate of Incorporation', 'certificate-of-incorporation'),
            self::category(2, 'Memorandum of Association', 'memorandum-of-association'),
            self::category(3, 'Articles of Association', 'articles-of-association'),
            self::category(4, 'Trade Licence', 'trade-licence'),
            self::category(5, 'TIN Certificate', 'tin-certificate'),
            self::category(6, 'BIN/VAT Registration Certificate', 'bin-vat-registration-certificate'),
            self::category(7, 'Bank Solvency Certificate', 'bank-solvency-certificate'),
            self::category(8, 'Bank Account Documents', 'bank-account-documents'),
            self::category(9, 'Board Resolution', 'board-resolution'),
            self::category(10, 'RJSC Documents', 'rjsc-documents'),
            self::category(11, 'Shareholding Documents', 'shareholding-documents'),
            self::category(12, 'ISO Certificates', 'iso-certificates'),
            self::category(13, 'Electrical Contractor Licence', 'electrical-contractor-licence'),
            self::category(14, 'Manufacturer Authorization', 'manufacturer-authorization'),
            self::category(15, 'Distributor Authorization', 'distributor-authorization'),
            self::category(16, 'Experience Certificate', 'experience-certificate'),
            self::category(17, 'Project Completion Certificate', 'project-completion-certificate'),
            self::category(18, 'Tax Clearance Certificate', 'tax-clearance-certificate'),
            self::category(19, 'Insurance Certificate', 'insurance-certificate'),
            self::category(20, 'Custom Documents', 'custom-documents'),
        ];
    }

    /**
     * @return array{id: int, name: string, slug: string, sort_order: int}
     */
    private static function category(int $id, string $name, string $slug): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
            'sort_order' => $id,
        ];
    }
}

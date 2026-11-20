<?php

namespace App\Tax;

use App\Models\MushakInvoice;
use App\Models\MushakInvoiceLine;
use Illuminate\Support\Collection;

/**
 * An unsaved draft used to prove Bengali shaping. It is not written to the database.
 */
final class MushakSample
{
    public const REGISTERED = 'নিবন্ধিত ব্যক্তি · ক্রেতা';

    public const CONJUNCTS = 'জ্ঞান ক্ষতি বিদ্যা কর্ম বিজ্ঞান স্থান দ্ধ';

    public const TITLE = 'কর চালানপত্র';

    public const RULE = '[ বিধি ৪০ এর উপ-বিধি (১) এর দফা (গ) ও দফা (চ) দ্রষ্টব্য ]';

    public const TAKA = '৳';

    /**
     * @return array{invoice: MushakInvoice, lines: Collection<int, MushakInvoiceLine>, draftCompany: array{legal_name: string, bin: string, address: list<string>}}
     */
    public function viewData(): array
    {
        $invoice = new MushakInvoice([
            'status' => 'draft',
            'number' => '',
            'buyer_name' => self::REGISTERED,
            'buyer_bin' => '',
            'destination' => self::CONJUNCTS,
            'responsible_name' => self::TITLE,
            'responsible_designation' => self::RULE,
            'subtotal_base' => '1000.0000',
            'total_vat' => '150.0000',
            'total_supplementary_duty' => '0.0000',
            'total_adjustment' => '0.0000',
            'total_final' => '1150.0000',
            'calculation_precision' => 4,
            'rounding_method' => 'half_up',
            'tax_review_status' => 'review_required',
        ]);
        $invoice->document_date = '2026-11-20';

        $line = new MushakInvoiceLine([
            'position' => 1,
            'description' => self::REGISTERED.' '.self::CONJUNCTS.' '.self::TAKA,
            'unit' => 'job',
            'quantity' => '1.0000',
            'source_unit_price' => '1000.00',
            'base_amount' => '1000.0000',
            'vat_rate_percent' => '15.0000',
            'vat_amount' => '150.0000',
            'supplementary_duty_amount' => '0.0000',
        ]);

        return [
            'invoice' => $invoice,
            'lines' => new Collection([$line]),
            'draftCompany' => [
                'legal_name' => 'Ahmad Solution Ltd.',
                'bin' => '000000000-0000',
                'address' => ['12 Green Road, Dhanmondi, Dhaka'],
            ],
        ];
    }
}

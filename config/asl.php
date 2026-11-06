<?php

return [

    'company' => [
        'legal_name' => 'Ahmad Solution Ltd.',
        'country' => 'Bangladesh',
        'currency' => 'BDT',
        'currency_symbol' => '৳',
    ],

    /*
    | Comma-separated proxy addresses. Leave empty unless HTTPS is terminated
    | in front of PHP. Do not use "*" on shared hosting.
    */
    /*
    | Private document uploads. Sizes are kilobytes. Keep the file limit below
    | the host upload_max_filesize and post_max_size (20M in public/.user.ini).
    */
    'documents' => [
        'max_kilobytes' => (int) env('DOCUMENT_MAX_KILOBYTES', 10240),
        'max_files' => 5,
        'extensions' => ['pdf', 'jpg', 'jpeg', 'png'],
        'mimes' => ['application/pdf', 'image/jpeg', 'image/png'],
        'logo_max_kilobytes' => (int) env('LOGO_MAX_KILOBYTES', 2048),
        'logo_extensions' => ['jpg', 'jpeg', 'png'],
        'logo_mimes' => ['image/jpeg', 'image/png'],
    ],

    /*
    | Quotation arithmetic and share-link limits. Tax is not calculated.
    | Quantity allows 4 decimal places. Money stays at poisha (2 decimal places).
    */
    'quotations' => [
        'number_prefix' => 'QT',
        'quantity_scale' => 4,
        'max_quantity' => '9999999.9999',
        'max_unit_price' => '99999999.99',
        'max_lines' => 50,
        'share_default_days' => 14,
        'share_max_days' => 90,
        'read_per_minute' => 30,
        'read_ip_per_minute' => 60,
        'respond_per_hour' => 5,
        'respond_ip_per_minute' => 10,
        'tax_notice' => 'Tax is excluded from the amounts on this quotation. Tax is subject to confirmation and is not calculated here. This document is not a tax invoice, a Mushak form, or a delivery challan.',
    ],

    /*
    | Delivery challans and commercial invoices. Numbers are assigned when the
    | document is issued, not when the draft is saved. Tax is not calculated.
    | A challan is not an invoice. A commercial invoice is not a Mushak form.
    */
    'delivery_challans' => [
        'number_prefix' => 'DC',
        'notice' => 'This delivery challan is not a commercial invoice, a tax invoice, or a Mushak form. Generating this PDF does not record dispatch or receipt.',
    ],

    'commercial_invoices' => [
        'number_prefix' => 'CI',
        'notice' => 'This is a commercial invoice. Tax is excluded and is not calculated. This document is not a tax invoice or a Mushak form.',
    ],

    /*
    | Mushak 6.3. The VAT percentage is typed on the document. These settings
    | do not choose a rate. BDT on the form face is shown at 2 decimal places.
    */
    'mushak' => [
        'number_prefix' => 'M63',
        'rate_notice' => 'The VAT percentage on this document was entered by the issuer. It was not selected or certified by this system. Rounding / Decimal Adjustment is not VAT.',
    ],

    'trusted_proxies' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('TRUSTED_PROXIES', '')),
    ))),

];

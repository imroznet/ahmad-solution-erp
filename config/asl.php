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

    'trusted_proxies' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('TRUSTED_PROXIES', '')),
    ))),

];

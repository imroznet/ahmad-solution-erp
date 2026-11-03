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
    'trusted_proxies' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('TRUSTED_PROXIES', '')),
    ))),

];

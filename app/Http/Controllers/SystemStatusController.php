<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class SystemStatusController extends Controller
{
    public function __invoke(): View
    {
        $catalog = [
            'bcmath' => 'Recommended. Decimal arithmetic uses it when it is loaded, and a pure-PHP fallback otherwise.',
            'gmp' => 'Optional. brick/math prefers GMP when the extension is loaded.',
            'intl' => 'Not required. An earlier hosting check reported this extension as unavailable.',
            'pdo_mysql' => 'Required for the MySQL database.',
            'mbstring' => 'Required by Laravel.',
            'openssl' => 'Required by Laravel.',
            'tokenizer' => 'Required by Laravel.',
            'xml' => 'Required by Laravel.',
            'curl' => 'Required by Laravel.',
            'fileinfo' => 'Required by Laravel.',
            'gd' => 'Reserved for later image handling.',
            'zip' => 'Reserved for later document packages.',
        ];

        $extensions = [];

        foreach ($catalog as $name => $description) {
            $extensions[] = [
                'name' => $name,
                'loaded' => extension_loaded($name),
                'description' => $description,
            ];
        }

        return view('system.status', [
            'extensions' => $extensions,
            'database' => $this->databaseStatus(),
            'phpVersion' => PHP_VERSION,
            'environment' => app()->environment(),
            'debug' => (bool) config('app.debug'),
            'timezone' => (string) config('app.timezone'),
            'queue' => (string) config('queue.default'),
            'sessionDriver' => (string) config('session.driver'),
            'decimal' => $this->decimalCalculator(),
        ]);
    }

    private function databaseStatus(): string
    {
        try {
            DB::select('select 1 as ok');

            return 'connected';
        } catch (Throwable) {
            return 'unavailable';
        }
    }

    private function decimalCalculator(): string
    {
        if (extension_loaded('gmp')) {
            return 'gmp';
        }

        if (extension_loaded('bcmath')) {
            return 'bcmath';
        }

        return 'pure PHP';
    }
}

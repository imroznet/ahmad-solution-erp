<?php

namespace App\Console\Commands;

use App\Quotations\QuotationModuleSql;
use Illuminate\Console\Command;

class ExportQuotationsSqlCommand extends Command
{
    protected $signature = 'asl:export-quotations-sql';

    protected $description = 'Write the quotations-module SQL used for a phpMyAdmin upgrade';

    public function handle(): int
    {
        $path = base_path('deploy/upgrade-quotations-module.sql');
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->error('Unable to create '.$directory);

            return self::FAILURE;
        }

        file_put_contents($path, QuotationModuleSql::upgrade());
        $this->info('Wrote '.$path);

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Tax\TaxModuleSql;
use Illuminate\Console\Command;

class ExportTaxSqlCommand extends Command
{
    protected $signature = 'asl:export-tax-sql';

    protected $description = 'Write the tax-module SQL used for a phpMyAdmin upgrade';

    public function handle(): int
    {
        $path = base_path('deploy/upgrade-tax-module.sql');
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->error('Unable to create '.$directory);

            return self::FAILURE;
        }

        file_put_contents($path, TaxModuleSql::upgrade());
        $this->info('Wrote '.$path);

        return self::SUCCESS;
    }
}

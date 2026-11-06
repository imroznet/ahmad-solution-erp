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
        file_put_contents($path, TaxModuleSql::upgrade());
        $this->info('Wrote '.$path);

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Company\CompanyModuleSql;
use Illuminate\Console\Command;

class ExportCompanySqlCommand extends Command
{
    protected $signature = 'asl:export-company-sql';

    protected $description = 'Write the company-module SQL used for a phpMyAdmin install or upgrade';

    public function handle(): int
    {
        $this->write(base_path('deploy/company-reference-data.sql'), CompanyModuleSql::categories());
        $this->write(base_path('deploy/upgrade-company-module.sql'), CompanyModuleSql::upgrade());

        return self::SUCCESS;
    }

    private function write(string $path, string $sql): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->error('Unable to create '.$directory);

            return;
        }

        file_put_contents($path, $sql);
        $this->info('Wrote '.$path);
    }
}

<?php

namespace App\Console\Commands;

use App\Units\UnitModuleSql;
use Illuminate\Console\Command;

class ExportUnitsSqlCommand extends Command
{
    protected $signature = 'asl:export-units-sql';

    protected $description = 'Write the measurement-unit SQL used for a phpMyAdmin upgrade';

    public function handle(): int
    {
        $path = base_path('deploy/upgrade-units-module.sql');
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->error('Unable to create '.$directory);

            return self::FAILURE;
        }

        file_put_contents($path, UnitModuleSql::upgrade());
        $this->info('Wrote '.$path);

        return self::SUCCESS;
    }
}

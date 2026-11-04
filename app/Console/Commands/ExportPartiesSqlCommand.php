<?php

namespace App\Console\Commands;

use App\Parties\PartyModuleSql;
use Illuminate\Console\Command;

class ExportPartiesSqlCommand extends Command
{
    protected $signature = 'asl:export-parties-sql';

    protected $description = 'Write the parties-module SQL used for a phpMyAdmin upgrade';

    public function handle(): int
    {
        $path = base_path('deploy/upgrade-parties-module.sql');
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->error('Unable to create '.$directory);

            return self::FAILURE;
        }

        file_put_contents($path, PartyModuleSql::upgrade());
        $this->info('Wrote '.$path);

        return self::SUCCESS;
    }
}

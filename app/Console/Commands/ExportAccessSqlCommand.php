<?php

namespace App\Console\Commands;

use App\Authorization\AccessSqlExporter;
use Illuminate\Console\Command;

class ExportAccessSqlCommand extends Command
{
    protected $signature = 'asl:export-access-sql {--path=deploy/reference-data.sql : Path relative to the application root}';

    protected $description = 'Write the role and permission SQL used for a phpMyAdmin first install';

    public function handle(): int
    {
        $path = base_path((string) $this->option('path'));
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->error('Unable to create '.$directory);

            return self::FAILURE;
        }

        file_put_contents($path, AccessSqlExporter::toSql());
        $this->info('Wrote '.$path);

        return self::SUCCESS;
    }
}

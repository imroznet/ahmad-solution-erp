<?php

namespace App\Console\Commands;

use App\Documents\DocumentModuleSql;
use Illuminate\Console\Command;

class ExportDocumentsSqlCommand extends Command
{
    protected $signature = 'asl:export-delivery-documents-sql';

    protected $description = 'Write the delivery-document SQL used for a phpMyAdmin upgrade';

    public function handle(): int
    {
        $path = base_path('deploy/upgrade-delivery-documents-module.sql');
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->error('Unable to create '.$directory);

            return self::FAILURE;
        }

        file_put_contents($path, DocumentModuleSql::upgrade());
        $this->info('Wrote '.$path);

        return self::SUCCESS;
    }
}

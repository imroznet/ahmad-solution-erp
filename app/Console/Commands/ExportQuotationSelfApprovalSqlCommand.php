<?php

namespace App\Console\Commands;

use App\Quotations\QuotationSelfApprovalSql;
use Illuminate\Console\Command;

class ExportQuotationSelfApprovalSqlCommand extends Command
{
    protected $signature = 'asl:export-quotation-self-approval-sql';

    protected $description = 'Write the quotation self-approval SQL used for a phpMyAdmin upgrade';

    public function handle(): int
    {
        $path = base_path('deploy/upgrade-quotation-self-approval.sql');
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->error('Unable to create '.$directory);

            return self::FAILURE;
        }

        file_put_contents($path, QuotationSelfApprovalSql::upgrade());
        $this->info('Wrote '.$path);

        return self::SUCCESS;
    }
}

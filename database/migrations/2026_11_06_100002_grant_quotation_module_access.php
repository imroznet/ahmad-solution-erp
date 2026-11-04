<?php

use App\Quotations\QuotationModuleInstaller;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(QuotationModuleInstaller::class)->install();
    }

    public function down(): void
    {
        // Permission rows stay. Dropping the quotation tables does not remove grants.
    }
};

<?php

use App\Quotations\QuotationSelfApprovalInstaller;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(QuotationSelfApprovalInstaller::class)->install();
    }

    public function down(): void
    {
        // The permission row stays. Removing it would hide a grant the owner saved.
    }
};

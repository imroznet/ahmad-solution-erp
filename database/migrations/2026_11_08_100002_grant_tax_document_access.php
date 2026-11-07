<?php

use App\Tax\TaxModuleInstaller;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(TaxModuleInstaller::class)->install();
    }

    public function down(): void
    {
        // Permission rows stay. Dropping the tax tables does not remove grants.
    }
};

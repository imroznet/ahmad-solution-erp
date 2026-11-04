<?php

use App\Company\CompanyModuleInstaller;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(CompanyModuleInstaller::class)->install();
    }

    public function down(): void
    {
        // Reference rows are left in place. Dropping the company tables removes categories.
    }
};

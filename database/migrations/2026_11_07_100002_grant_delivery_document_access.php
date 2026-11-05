<?php

use App\Documents\DocumentModuleInstaller;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(DocumentModuleInstaller::class)->install();
    }

    public function down(): void
    {
        // Permission rows stay. Dropping the document tables does not remove grants.
    }
};

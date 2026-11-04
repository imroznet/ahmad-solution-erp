<?php

use App\Parties\PartyModuleInstaller;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(PartyModuleInstaller::class)->install();
    }

    public function down(): void
    {
        // Permission rows stay. Dropping the party tables does not remove grants.
    }
};

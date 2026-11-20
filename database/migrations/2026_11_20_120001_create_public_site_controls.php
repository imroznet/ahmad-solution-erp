<?php

use App\PublicSite\PublicSiteInstaller;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('public_site_controls')) {
            Schema::create('public_site_controls', function (Blueprint $table): void {
                $table->id();
                $table->boolean('maintenance_enabled')->default(false);
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (DB::table('public_site_controls')->where('id', 1)->doesntExist()) {
            DB::table('public_site_controls')->insert([
                'id' => 1,
                'maintenance_enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(PublicSiteInstaller::class)->install();
    }

    public function down(): void
    {
        Schema::dropIfExists('public_site_controls');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('public_site_controls')) {
            return;
        }

        Schema::table('public_site_controls', function (Blueprint $table): void {
            if (! Schema::hasColumn('public_site_controls', 'contact_form_enabled')) {
                $table->boolean('contact_form_enabled')->default(true);
            }
            if (! Schema::hasColumn('public_site_controls', 'recaptcha_enabled')) {
                $table->boolean('recaptcha_enabled')->default(false);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('public_site_controls')) {
            return;
        }

        Schema::table('public_site_controls', function (Blueprint $table): void {
            if (Schema::hasColumn('public_site_controls', 'recaptcha_enabled')) {
                $table->dropColumn('recaptcha_enabled');
            }
            if (Schema::hasColumn('public_site_controls', 'contact_form_enabled')) {
                $table->dropColumn('contact_form_enabled');
            }
        });
    }
};

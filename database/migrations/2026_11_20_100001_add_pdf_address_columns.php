<?php

use App\Company\AddressType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('company_addresses') && ! Schema::hasColumn('company_addresses', 'show_on_pdf')) {
            Schema::table('company_addresses', function (Blueprint $table) {
                $table->boolean('show_on_pdf')->default(false)->after('is_active');
            });

            DB::table('company_addresses')
                ->where('is_active', true)
                ->whereIn('address_type', AddressType::exclusiveActive())
                ->update(['show_on_pdf' => true]);
        }

        if (Schema::hasTable('party_addresses') && ! Schema::hasColumn('party_addresses', 'post_office')) {
            Schema::table('party_addresses', function (Blueprint $table) {
                $table->string('post_office', 120)->nullable()->after('postcode');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('company_addresses') && Schema::hasColumn('company_addresses', 'show_on_pdf')) {
            Schema::table('company_addresses', function (Blueprint $table) {
                $table->dropColumn('show_on_pdf');
            });
        }

        if (Schema::hasTable('party_addresses') && Schema::hasColumn('party_addresses', 'post_office')) {
            Schema::table('party_addresses', function (Blueprint $table) {
                $table->dropColumn('post_office');
            });
        }
    }
};

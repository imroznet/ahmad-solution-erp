<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_revisions', function (Blueprint $table) {
            $table->decimal('vat_rate_percent', 7, 4)->nullable()->after('total');
            $table->decimal('tax_rate_percent', 7, 4)->nullable()->after('vat_rate_percent');
            $table->decimal('vat_amount', 18, 2)->nullable()->after('tax_rate_percent');
            $table->decimal('tax_amount', 18, 2)->nullable()->after('vat_amount');
            $table->decimal('adjustment_amount', 18, 2)->default('0.00')->after('tax_amount');
            $table->text('adjustment_reason')->nullable()->after('adjustment_amount');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_revisions', function (Blueprint $table) {
            $table->dropColumn([
                'vat_rate_percent',
                'tax_rate_percent',
                'vat_amount',
                'tax_amount',
                'adjustment_amount',
                'adjustment_reason',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->challanLines();
        $this->invoiceLines();
    }

    public function down(): void
    {
        $this->reverseInvoiceLines();
        $this->reverseChallanLines();
    }

    private function challanLines(): void
    {
        if (Schema::hasColumn('delivery_challan_lines', 'superseded_at')) {
            return;
        }

        Schema::table('delivery_challan_lines', function (Blueprint $table): void {
            $table->timestamp('superseded_at')->nullable();
            $table->foreignId('superseded_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('delivery_challan_lines', function (Blueprint $table): void {
            $table->unsignedSmallInteger('active_position')->nullable()->storedAs('case when superseded_at is null then position else null end');
            $table->unsignedBigInteger('active_quotation_line_id')->nullable()->storedAs('case when superseded_at is null then quotation_line_id else null end');
            $table->unique(['delivery_challan_id', 'active_position'], 'delivery_challan_lines_current_position_unique');
            $table->unique(['delivery_challan_id', 'active_quotation_line_id'], 'delivery_challan_lines_current_line_unique');
        });

        Schema::table('delivery_challan_lines', function (Blueprint $table): void {
            $table->dropUnique('delivery_challan_lines_challan_position_unique');
            $table->dropUnique('delivery_challan_lines_challan_line_unique');
        });
    }

    private function invoiceLines(): void
    {
        if (Schema::hasColumn('commercial_invoice_lines', 'superseded_at')) {
            return;
        }

        Schema::table('commercial_invoice_lines', function (Blueprint $table): void {
            $table->timestamp('superseded_at')->nullable();
            $table->foreignId('superseded_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('commercial_invoice_lines', function (Blueprint $table): void {
            $table->unsignedSmallInteger('active_position')->nullable()->storedAs('case when superseded_at is null then position else null end');
            $table->unsignedBigInteger('active_challan_line_id')->nullable()->storedAs('case when superseded_at is null then delivery_challan_line_id else null end');
            $table->unique(['commercial_invoice_id', 'active_position'], 'commercial_invoice_lines_current_position_unique');
            $table->unique(['commercial_invoice_id', 'active_challan_line_id'], 'commercial_invoice_lines_current_challan_unique');
        });

        Schema::table('commercial_invoice_lines', function (Blueprint $table): void {
            $table->dropUnique('commercial_invoice_lines_invoice_position_unique');
            $table->dropUnique('commercial_invoice_lines_invoice_challan_unique');
        });
    }

    private function reverseChallanLines(): void
    {
        if (! Schema::hasColumn('delivery_challan_lines', 'superseded_at')) {
            return;
        }

        Schema::table('delivery_challan_lines', function (Blueprint $table): void {
            $table->unique(['delivery_challan_id', 'position'], 'delivery_challan_lines_challan_position_unique');
            $table->unique(['delivery_challan_id', 'quotation_line_id'], 'delivery_challan_lines_challan_line_unique');
        });

        Schema::table('delivery_challan_lines', function (Blueprint $table): void {
            $table->dropUnique('delivery_challan_lines_current_position_unique');
            $table->dropUnique('delivery_challan_lines_current_line_unique');
            $table->dropColumn(['active_position', 'active_quotation_line_id']);
            $table->dropConstrainedForeignId('superseded_by');
            $table->dropColumn('superseded_at');
        });
    }

    private function reverseInvoiceLines(): void
    {
        if (! Schema::hasColumn('commercial_invoice_lines', 'superseded_at')) {
            return;
        }

        Schema::table('commercial_invoice_lines', function (Blueprint $table): void {
            $table->unique(['commercial_invoice_id', 'position'], 'commercial_invoice_lines_invoice_position_unique');
            $table->unique(['commercial_invoice_id', 'delivery_challan_line_id'], 'commercial_invoice_lines_invoice_challan_unique');
        });

        Schema::table('commercial_invoice_lines', function (Blueprint $table): void {
            $table->dropUnique('commercial_invoice_lines_current_position_unique');
            $table->dropUnique('commercial_invoice_lines_current_challan_unique');
            $table->dropColumn(['active_position', 'active_challan_line_id']);
            $table->dropConstrainedForeignId('superseded_by');
            $table->dropColumn('superseded_at');
        });
    }
};

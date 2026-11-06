<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rules', function (Blueprint $table) {
            $table->id();
            $table->string('tax_type', 20);
            $table->string('rule_code', 40);
            $table->unsignedSmallInteger('version')->default(1);
            $table->unsignedBigInteger('supersedes_rule_id')->nullable();
            $table->string('supply_classification', 160)->default('');
            $table->decimal('documented_rate', 12, 4)->nullable();
            $table->string('rate_kind', 20)->default('not_a_rate');
            $table->text('calculation_basis')->nullable();
            $table->text('rounding_note')->nullable();
            $table->string('price_treatment', 20)->default('unspecified');
            $table->text('threshold_note')->nullable();
            $table->text('exemption_note')->nullable();
            $table->text('supporting_data_note')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('legal_instrument', 200);
            $table->string('section_reference', 200)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->date('publication_date')->nullable();
            $table->date('instrument_effective_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('approval_status', 20);
            $table->string('activity_status', 20);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retired_at')->nullable();
            $table->foreignId('retired_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tax_type', 'supply_classification', 'approval_status', 'activity_status'], 'tax_rules_scope_index');
            $table->unique(['tax_type', 'rule_code', 'version'], 'tax_rules_code_version_unique');
        });

        Schema::table('tax_rules', function (Blueprint $table) {
            $table->foreign('supersedes_rule_id')->references('id')->on('tax_rules')->nullOnDelete();
        });

        Schema::create('mushak_invoice_number_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('mushak_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->nullable();
            $table->foreignId('commercial_invoice_id')->constrained('commercial_invoices')->restrictOnDelete();
            $table->foreignId('quotation_id')->constrained('quotations')->restrictOnDelete();
            $table->string('status', 20);
            $table->string('buyer_source', 20);
            $table->foreignId('party_id')->nullable()->constrained('parties')->restrictOnDelete();
            $table->date('document_date');
            $table->string('destination', 200);
            $table->unsignedTinyInteger('calculation_precision');
            $table->string('rounding_method', 32);
            $table->string('tax_review_status', 40)->default('review_required');
            $table->text('tax_review_note')->nullable();
            $table->timestamp('tax_review_confirmed_at')->nullable();
            $table->foreignId('tax_review_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tax_rule_id')->nullable()->constrained('tax_rules')->restrictOnDelete();
            $table->json('rule_snapshot')->nullable();
            $table->string('seller_legal_name', 200)->nullable();
            $table->string('seller_bin', 30)->nullable();
            $table->string('seller_tin', 30)->nullable();
            $table->text('seller_address')->nullable();
            $table->string('seller_phone', 40)->nullable();
            $table->string('seller_email')->nullable();
            $table->json('seller_snapshot')->nullable();
            $table->string('buyer_name', 200)->nullable();
            $table->string('buyer_bin', 30)->nullable();
            $table->string('buyer_tin', 30)->nullable();
            $table->text('buyer_address')->nullable();
            $table->string('buyer_phone', 40)->nullable();
            $table->string('buyer_email')->nullable();
            $table->json('buyer_snapshot')->nullable();
            $table->string('responsible_name', 160)->nullable();
            $table->string('responsible_designation', 120)->nullable();
            $table->decimal('subtotal_base', 19, 4)->nullable();
            $table->decimal('total_vat', 19, 4)->nullable();
            $table->decimal('total_adjustment', 19, 4)->default(0);
            $table->decimal('total_final', 19, 4)->nullable();
            $table->decimal('total_supplementary_duty', 19, 4)->nullable();
            $table->char('currency_code', 3)->default('BDT');
            $table->json('calculation_snapshot')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('number', 'mushak_invoices_number_unique');
            $table->index(['commercial_invoice_id', 'status'], 'mushak_invoices_invoice_status_index');
        });

        Schema::create('mushak_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mushak_invoice_id')->constrained('mushak_invoices')->restrictOnDelete();
            $table->foreignId('commercial_invoice_line_id')->constrained('commercial_invoice_lines')->restrictOnDelete();
            $table->foreignId('quotation_line_id')->constrained('quotation_lines')->restrictOnDelete();
            $table->foreignId('delivery_challan_line_id')->constrained('delivery_challan_lines')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->text('description');
            $table->text('source_description');
            $table->string('unit', 40);
            $table->decimal('quantity', 16, 4);
            $table->decimal('source_unit_price', 16, 2);
            $table->decimal('source_line_total', 18, 2);
            $table->decimal('base_amount', 19, 4);
            $table->decimal('vat_rate_percent', 8, 4)->nullable();
            $table->decimal('vat_amount', 19, 4)->nullable();
            $table->decimal('forward_vat_amount', 19, 4)->nullable();
            $table->boolean('residual_applied')->default(false);
            $table->decimal('adjustment_amount', 19, 4)->default(0);
            $table->string('adjustment_reason', 500)->nullable();
            $table->foreignId('adjustment_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('adjustment_at')->nullable();
            $table->decimal('supplementary_duty_amount', 19, 4)->nullable();
            $table->decimal('final_amount', 19, 4)->nullable();
            $table->json('calculation_snapshot')->nullable();
            $table->foreignId('rate_entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rate_entered_at')->nullable();
            $table->timestamps();

            $table->unique(['mushak_invoice_id', 'position'], 'mushak_lines_position_unique');
            $table->unique(['mushak_invoice_id', 'commercial_invoice_line_id'], 'mushak_lines_source_unique');
        });

        Schema::create('mushak_invoice_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mushak_invoice_id')->constrained('mushak_invoices')->restrictOnDelete();
            $table->string('event', 40);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['mushak_invoice_id', 'id'], 'mushak_events_invoice_index');
        });

        Schema::create('vds_records', function (Blueprint $table) {
            $this->deduction($table, 'vds');
        });

        Schema::create('ait_records', function (Blueprint $table) {
            $this->deduction($table, 'ait');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ait_records');
        Schema::dropIfExists('vds_records');
        Schema::dropIfExists('mushak_invoice_events');
        Schema::dropIfExists('mushak_invoice_lines');
        Schema::dropIfExists('mushak_invoices');
        Schema::dropIfExists('mushak_invoice_number_sequences');
        Schema::dropIfExists('tax_rules');
    }

    private function deduction(Blueprint $table, string $prefix): void
    {
        $table->id();
        $table->string('direction', 32);
        $table->string('status', 20);
        $table->foreignId('party_id')->nullable()->constrained('parties')->restrictOnDelete();
        $table->string('counterparty_name', 200);
        $table->string('counterparty_bin', 30)->nullable();
        $table->string('counterparty_tin', 30)->nullable();
        $table->foreignId('commercial_invoice_id')->nullable()->constrained('commercial_invoices')->restrictOnDelete();
        $table->foreignId('mushak_invoice_id')->nullable()->constrained('mushak_invoices')->restrictOnDelete();
        $table->string('source_reference', 120)->nullable();
        $table->decimal('taxable_basis', 19, 4)->nullable();
        $table->decimal('deducted_amount', 19, 4)->nullable();
        $table->date('record_date');
        $table->string('certificate_reference', 120)->nullable();
        $table->string('treasury_reference', 120)->nullable();
        $table->foreignId('tax_rule_id')->nullable()->constrained('tax_rules')->restrictOnDelete();
        $table->json('rule_snapshot')->nullable();
        $table->string('certificate_form', 40)->default('unverified');
        $table->string('review_status', 40)->default('review_required');
        $table->text('notes')->nullable();
        $table->text('internal_notes')->nullable();
        $table->timestamp('recorded_at')->nullable();
        $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('voided_at')->nullable();
        $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
        $table->text('void_reason')->nullable();
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamps();

        $table->index(['direction', 'status'], $prefix.'_records_direction_status_index');
    }
};

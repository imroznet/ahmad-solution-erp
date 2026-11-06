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
            $table->string('supply_classification', 80)->default('');
            $table->string('classification_key', 80)->default('');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tax_type', 'rule_code'], 'tax_rules_type_code_unique');
            $table->index(['tax_type', 'classification_key'], 'tax_rules_type_classification_index');
        });

        Schema::create('tax_rule_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_rule_id')->constrained('tax_rules')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 20);
            $table->string('rate_kind', 20)->default('none');
            $table->decimal('rate_value', 7, 4)->nullable();
            $table->decimal('fixed_amount', 20, 4)->nullable();
            $table->string('calculation_basis', 80)->nullable();
            $table->text('threshold_note')->nullable();
            $table->text('exemption_note')->nullable();
            $table->string('legal_instrument', 200);
            $table->string('section_reference', 200);
            $table->string('source_url', 500);
            $table->date('publication_date')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('retired_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retired_at')->nullable();
            $table->string('retirement_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['tax_rule_id', 'version_number'], 'tax_rule_versions_rule_version_unique');
            $table->index(['status', 'effective_from'], 'tax_rule_versions_status_from_index');
        });

        Schema::create('mushak_number_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('mushak_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->nullable();
            $table->foreignId('commercial_invoice_id')->constrained('commercial_invoices')->restrictOnDelete();
            $table->string('status', 20);
            $table->date('document_date');
            $table->string('supply_destination', 200)->nullable();
            $table->string('supply_classification', 80)->default('');
            $table->string('buyer_source', 20);
            $table->foreignId('party_id')->nullable()->constrained('parties')->restrictOnDelete();
            $table->string('buyer_name', 200);
            $table->string('buyer_bin', 40)->nullable();
            $table->string('buyer_tin', 40)->nullable();
            $table->text('buyer_address');
            $table->string('buyer_phone', 40)->nullable();
            $table->string('buyer_email', 160)->nullable();
            $table->unsignedTinyInteger('calculation_precision');
            $table->string('rounding_method', 32);
            $table->string('currency_code', 3)->default('BDT');
            $table->decimal('base_total', 20, 4)->nullable();
            $table->decimal('vat_total', 20, 4)->nullable();
            $table->decimal('adjustment_total', 20, 4)->nullable();
            $table->decimal('final_total', 20, 4)->nullable();
            $table->foreignId('tax_rule_version_id')->nullable()->constrained('tax_rule_versions')->restrictOnDelete();
            $table->text('rule_explanation')->nullable();
            $table->string('tax_review_status', 40)->default('pending');
            $table->boolean('legal_treatment_confirmed')->default(false);
            $table->text('legal_treatment_note')->nullable();
            $table->text('review_reason')->nullable();
            $table->text('rate_notice')->nullable();
            $table->json('company_snapshot')->nullable();
            $table->json('source_snapshot')->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('prepared_by_designation', 120)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('number', 'mushak_invoices_number_unique');
            $table->index(['commercial_invoice_id', 'status'], 'mushak_invoices_invoice_status_index');
        });

        Schema::create('mushak_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mushak_invoice_id')->constrained('mushak_invoices')->restrictOnDelete();
            $table->foreignId('commercial_invoice_line_id')->nullable()->constrained('commercial_invoice_lines')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('description', 2000);
            $table->string('unit', 40);
            $table->decimal('quantity', 16, 4);
            $table->decimal('base_amount', 20, 4);
            $table->decimal('vat_rate', 7, 4)->nullable();
            $table->decimal('vat_amount', 20, 4)->nullable();
            $table->decimal('adjustment_amount', 20, 4)->default(0);
            $table->string('adjustment_reason', 500)->nullable();
            $table->decimal('final_amount', 20, 4)->nullable();
            $table->decimal('residual_amount', 20, 4)->default(0);
            $table->string('calculation_origin', 20);
            $table->foreignId('rate_entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rate_entered_at')->nullable();
            $table->foreignId('adjustment_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('adjustment_updated_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->foreignId('superseded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('active_position')->nullable()->storedAs('case when superseded_at is null then position else null end');
            $table->unsignedBigInteger('active_invoice_line_id')->nullable()->storedAs('case when superseded_at is null then commercial_invoice_line_id else null end');
            $table->timestamps();

            $table->unique(['mushak_invoice_id', 'active_position'], 'mushak_lines_current_position_unique');
            $table->unique(['mushak_invoice_id', 'active_invoice_line_id'], 'mushak_lines_current_source_unique');
        });

        Schema::create('mushak_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mushak_invoice_id')->constrained('mushak_invoices')->restrictOnDelete();
            $table->string('event', 40);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['mushak_invoice_id', 'id'], 'mushak_events_invoice_id_index');
        });

        Schema::create('vds_records', function (Blueprint $table) {
            $this->withholding($table);
        });

        Schema::create('ait_records', function (Blueprint $table) {
            $this->withholding($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ait_records');
        Schema::dropIfExists('vds_records');
        Schema::dropIfExists('mushak_events');
        Schema::dropIfExists('mushak_lines');
        Schema::dropIfExists('mushak_invoices');
        Schema::dropIfExists('mushak_number_sequences');
        Schema::dropIfExists('tax_rule_versions');
        Schema::dropIfExists('tax_rules');
    }

    private function withholding(Blueprint $table): void
    {
        $table->id();
        $table->string('direction', 40);
        $table->foreignId('party_id')->nullable()->constrained('parties')->restrictOnDelete();
        $table->string('party_name', 200);
        $table->string('party_bin', 40)->nullable();
        $table->string('party_tin', 40)->nullable();
        $table->foreignId('commercial_invoice_id')->nullable()->constrained('commercial_invoices')->restrictOnDelete();
        $table->foreignId('mushak_invoice_id')->nullable()->constrained('mushak_invoices')->restrictOnDelete();
        $table->foreignId('tax_rule_version_id')->nullable()->constrained('tax_rule_versions')->restrictOnDelete();
        $table->string('supply_description', 500);
        $table->decimal('taxable_basis', 20, 4)->nullable();
        $table->decimal('amount', 20, 4);
        $table->date('record_date');
        $table->string('certificate_reference', 80)->nullable();
        $table->string('payment_reference', 80)->nullable();
        $table->text('source_note')->nullable();
        $table->text('internal_notes')->nullable();
        $table->string('certificate_status', 40)->default('not_issued_form_unverified');
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamps();

        $table->index('record_date');
    }
};

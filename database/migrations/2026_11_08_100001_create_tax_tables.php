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
            $table->string('tax_type', 32);
            $table->string('rule_key', 80);
            $table->unsignedInteger('version');
            $table->foreignId('supersedes_rule_id')->nullable();
            $table->string('supply_classification', 120)->nullable();
            $table->string('name', 200);
            $table->decimal('rate', 9, 4)->nullable();
            $table->string('rate_kind', 32)->nullable();
            $table->string('calculation_basis', 80)->nullable();
            $table->string('rounding_rule', 80)->nullable();
            $table->string('price_treatment', 32)->nullable();
            $table->decimal('threshold_amount', 18, 2)->nullable();
            $table->text('exemption_note')->nullable();
            $table->text('required_supporting_data')->nullable();
            $table->string('legal_instrument', 200);
            $table->string('section_reference', 200)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->text('notes')->nullable();
            $table->date('publication_date')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 32);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('retired_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->unique(['rule_key', 'version'], 'tax_rules_key_version_unique');
            $table->index(['tax_type', 'status', 'effective_from'], 'tax_rules_type_status_from_index');
            $table->foreign('supersedes_rule_id')->references('id')->on('tax_rules')->restrictOnDelete();
        });

        Schema::create('tax_rule_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_rule_id')->constrained('tax_rules')->restrictOnDelete();
            $table->string('event', 40);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tax_rule_id', 'id'], 'tax_rule_events_rule_id_index');
        });

        Schema::create('mushak_number_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('mushak_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commercial_invoice_id')->nullable()->constrained('commercial_invoices')->restrictOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->restrictOnDelete();
            $table->string('status', 20);
            $table->string('number', 32)->nullable();
            $table->string('buyer_source', 20);
            $table->foreignId('party_id')->nullable()->constrained('parties')->restrictOnDelete();
            $table->string('buyer_name', 200)->nullable();
            $table->string('buyer_bin', 30)->nullable();
            $table->string('buyer_tin', 30)->nullable();
            $table->text('buyer_address')->nullable();
            $table->string('buyer_phone', 40)->nullable();
            $table->string('buyer_email')->nullable();
            $table->string('destination', 300)->nullable();
            $table->string('responsible_name', 200)->nullable();
            $table->string('responsible_designation', 120)->nullable();
            $table->date('issue_date')->nullable();
            $table->char('issue_time', 5)->nullable();
            $table->char('currency_code', 3)->default('BDT');
            $table->decimal('total_value', 18, 2)->nullable();
            $table->decimal('total_supplementary_duty', 18, 2)->nullable();
            $table->decimal('total_vat', 18, 2)->nullable();
            $table->decimal('total_inclusive', 18, 2)->nullable();
            $table->string('tax_review_status', 40)->default('review_required');
            $table->boolean('rate_determined_by_erp')->default(false);
            $table->foreignId('treatment_rule_id')->nullable()->constrained('tax_rules')->restrictOnDelete();
            $table->json('treatment_rule_snapshot')->nullable();
            $table->text('treatment_note')->nullable();
            $table->foreignId('treatment_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('treatment_recorded_at')->nullable();
            $table->json('company_snapshot')->nullable();
            $table->json('customer_snapshot')->nullable();
            $table->json('source_references')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('manual_vat_notice');
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('number', 'mushak_documents_number_unique');
            $table->index(['status', 'id'], 'mushak_documents_status_id_index');
        });

        Schema::create('mushak_document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mushak_document_id')->constrained('mushak_documents')->restrictOnDelete();
            $table->foreignId('commercial_invoice_line_id')->nullable()->constrained('commercial_invoice_lines')->restrictOnDelete();
            $table->foreignId('quotation_line_id')->nullable()->constrained('quotation_lines')->restrictOnDelete();
            $table->foreignId('delivery_challan_line_id')->nullable()->constrained('delivery_challan_lines')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->text('description');
            $table->string('unit', 40)->nullable();
            $table->decimal('quantity', 16, 4)->nullable();
            $table->decimal('unit_price', 16, 2)->nullable();
            $table->decimal('line_value', 18, 2)->nullable();
            $table->decimal('supplementary_duty', 18, 2)->nullable();
            $table->decimal('vat_rate', 9, 4)->nullable();
            $table->string('specific_tax_note', 120)->nullable();
            $table->decimal('vat_amount', 18, 2)->nullable();
            $table->decimal('inclusive_value', 18, 2)->nullable();
            $table->foreignId('vat_rate_entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('vat_rate_entered_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->foreignId('superseded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unsignedSmallInteger('active_position')->nullable()->storedAs('case when superseded_at is null then position else null end');

            $table->unique(['mushak_document_id', 'active_position'], 'mushak_lines_current_position_unique');
        });

        Schema::create('mushak_document_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mushak_document_id')->constrained('mushak_documents')->restrictOnDelete();
            $table->string('event', 40);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['mushak_document_id', 'id'], 'mushak_document_events_document_id_index');
        });

        $this->withholdingTable('vds_records');
        $this->withholdingTable('ait_records');
    }

    public function down(): void
    {
        Schema::dropIfExists('ait_records');
        Schema::dropIfExists('vds_records');
        Schema::dropIfExists('mushak_document_events');
        Schema::dropIfExists('mushak_document_lines');
        Schema::dropIfExists('mushak_documents');
        Schema::dropIfExists('mushak_number_sequences');
        Schema::dropIfExists('tax_rule_events');
        Schema::dropIfExists('tax_rules');
    }

    private function withholdingTable(string $table): void
    {
        Schema::create($table, function (Blueprint $table): void {
            $table->id();
            $table->string('direction', 32);
            $table->string('status', 20);
            $table->string('counterparty_source', 20);
            $table->foreignId('party_id')->nullable()->constrained('parties')->restrictOnDelete();
            $table->string('counterparty_name', 200);
            $table->string('counterparty_bin', 30)->nullable();
            $table->string('counterparty_tin', 30)->nullable();
            $table->foreignId('mushak_document_id')->nullable()->constrained('mushak_documents')->restrictOnDelete();
            $table->foreignId('commercial_invoice_id')->nullable()->constrained('commercial_invoices')->restrictOnDelete();
            $table->string('underlying_reference', 200);
            $table->decimal('basis_amount', 18, 2)->nullable();
            $table->decimal('deducted_amount', 18, 2);
            $table->date('record_date');
            $table->string('certificate_number', 80)->nullable();
            $table->string('treasury_reference', 160)->nullable();
            $table->foreignId('tax_rule_id')->nullable()->constrained('tax_rules')->restrictOnDelete();
            $table->json('rule_snapshot')->nullable();
            $table->string('tax_review_status', 40)->default('review_required');
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['direction', 'status'], $table->getTable().'_direction_status_index');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mushak_63_number_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamp('updated_at')->nullable();
            $table->primary('year');
        });

        Schema::create('tax_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40);
            $table->string('tax_type', 20);
            $table->string('name', 160);
            $table->string('supply_classification', 160)->nullable();
            $table->string('status', 20);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('code', 'tax_rules_code_unique');
        });

        Schema::create('tax_rule_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_rule_id')->constrained('tax_rules')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('rate_percent', 7, 4)->nullable();
            $table->decimal('fixed_amount', 18, 2)->nullable();
            $table->string('calculation_basis', 40);
            $table->string('rounding_rule', 40);
            $table->string('price_treatment', 20);
            $table->decimal('threshold_amount', 18, 2)->nullable();
            $table->text('exemption_note')->nullable();
            $table->string('legal_instrument', 200);
            $table->string('section_reference', 200)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->date('publication_date')->nullable();
            $table->date('legally_effective_on')->nullable();
            $table->text('notes')->nullable();
            $table->string('approval_status', 20);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retired_at')->nullable();
            $table->foreignId('retired_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tax_rule_id', 'version_number'], 'tax_rule_versions_rule_version_unique');
            $table->index(['tax_rule_id', 'approval_status'], 'tax_rule_versions_rule_status_index');
        });

        Schema::create('mushak_63_documents', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->nullable();
            $table->foreignId('commercial_invoice_id')->constrained('commercial_invoices')->restrictOnDelete();
            $table->string('status', 20);
            $table->date('document_date');
            $table->string('buyer_source', 20);
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->string('buyer_legal_name', 200);
            $table->string('buyer_trading_name', 200)->nullable();
            $table->string('buyer_bin', 30)->nullable();
            $table->string('buyer_tin', 30)->nullable();
            $table->string('buyer_phone', 40)->nullable();
            $table->string('buyer_email')->nullable();
            $table->json('buyer_address')->nullable();
            $table->json('company_snapshot')->nullable();
            $table->string('invoice_number_snapshot', 32)->nullable();
            $table->date('invoice_date_snapshot')->nullable();
            $table->foreignId('tax_rule_version_id')->nullable()->constrained('tax_rule_versions')->nullOnDelete();
            $table->json('tax_rule_snapshot')->nullable();
            $table->string('review_status', 40);
            $table->timestamp('treatment_confirmed_at')->nullable();
            $table->foreignId('treatment_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('vat_notice');
            $table->string('form_source_url', 500);
            $table->char('form_sha256', 64);
            $table->decimal('base_total', 18, 2);
            $table->decimal('vat_total', 18, 2)->nullable();
            $table->decimal('inclusive_total', 18, 2)->nullable();
            $table->decimal('supplementary_duty_total', 18, 2)->nullable();
            $table->decimal('duties_total', 18, 2)->nullable();
            $table->char('currency_code', 3)->default('BDT');
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('number', 'mushak_63_documents_number_unique');
            $table->index(['commercial_invoice_id', 'status'], 'mushak_63_documents_invoice_status_index');
        });

        Schema::table('mushak_63_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('open_invoice_id')->nullable()->storedAs("case when status = 'voided' then null else commercial_invoice_id end");
            $table->unique('open_invoice_id', 'mushak_63_documents_open_invoice_unique');
        });

        Schema::create('mushak_63_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mushak_63_document_id')->constrained('mushak_63_documents')->restrictOnDelete();
            $table->foreignId('commercial_invoice_line_id')->nullable()->constrained('commercial_invoice_lines')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->text('description');
            $table->string('unit', 40);
            $table->decimal('quantity', 16, 4);
            $table->decimal('unit_price', 16, 2);
            $table->decimal('base_amount', 18, 2);
            $table->decimal('vat_rate_percent', 7, 4)->nullable();
            $table->decimal('vat_amount', 18, 2)->nullable();
            $table->decimal('inclusive_amount', 18, 2)->nullable();
            $table->decimal('supplementary_duty_amount', 18, 2)->nullable();
            $table->decimal('duties_amount', 18, 2)->nullable();
            $table->string('calculation_driver', 20);
            $table->foreignId('rate_entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rate_entered_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->foreignId('superseded_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('mushak_63_lines', function (Blueprint $table) {
            $table->unsignedSmallInteger('active_position')->nullable()->storedAs('case when superseded_at is null then position else null end');
            $table->unique(['mushak_63_document_id', 'active_position'], 'mushak_63_lines_current_position_unique');
        });

        Schema::create('mushak_63_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mushak_63_document_id')->constrained('mushak_63_documents')->restrictOnDelete();
            $table->string('event', 40);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['mushak_63_document_id', 'id'], 'mushak_63_events_document_id_index');
        });

        Schema::create('vds_records', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 32);
            $table->string('status', 20);
            $table->date('record_date');
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->string('counterparty_name', 200);
            $table->string('counterparty_bin', 30)->nullable();
            $table->string('counterparty_tin', 30)->nullable();
            $table->foreignId('commercial_invoice_id')->nullable()->constrained('commercial_invoices')->nullOnDelete();
            $table->foreignId('mushak_63_document_id')->nullable()->constrained('mushak_63_documents')->nullOnDelete();
            $table->decimal('taxable_basis', 18, 2);
            $table->decimal('tax_amount', 18, 2);
            $table->string('certificate_reference', 120)->nullable();
            $table->string('payment_reference', 120)->nullable();
            $table->foreignId('tax_rule_version_id')->nullable()->constrained('tax_rule_versions')->nullOnDelete();
            $table->json('tax_rule_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ait_records', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 32);
            $table->string('status', 20);
            $table->date('record_date');
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->string('counterparty_name', 200);
            $table->string('counterparty_bin', 30)->nullable();
            $table->string('counterparty_tin', 30)->nullable();
            $table->foreignId('commercial_invoice_id')->nullable()->constrained('commercial_invoices')->nullOnDelete();
            $table->foreignId('mushak_63_document_id')->nullable()->constrained('mushak_63_documents')->nullOnDelete();
            $table->decimal('taxable_basis', 18, 2);
            $table->decimal('tax_amount', 18, 2);
            $table->string('certificate_reference', 120)->nullable();
            $table->string('payment_reference', 120)->nullable();
            $table->foreignId('tax_rule_version_id')->nullable()->constrained('tax_rule_versions')->nullOnDelete();
            $table->json('tax_rule_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ait_records');
        Schema::dropIfExists('vds_records');
        Schema::dropIfExists('mushak_63_events');
        Schema::dropIfExists('mushak_63_lines');
        Schema::dropIfExists('mushak_63_documents');
        Schema::dropIfExists('tax_rule_versions');
        Schema::dropIfExists('tax_rules');
        Schema::dropIfExists('mushak_63_number_sequences');
    }
};

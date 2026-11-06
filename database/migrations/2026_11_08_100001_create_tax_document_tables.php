<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rule_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('rule_group_id');
            $table->unsignedInteger('version_number');
            $table->string('tax_type', 20);
            $table->string('supply_classification', 160);
            $table->string('rule_code', 80)->default('');
            $table->string('rate_basis', 32);
            $table->decimal('rate_percent', 9, 4)->nullable();
            $table->decimal('fixed_amount', 18, 2)->nullable();
            $table->char('currency_code', 3)->default('BDT');
            $table->string('calculation_basis', 32);
            $table->string('rounding_rule', 40);
            $table->decimal('threshold_amount', 18, 2)->nullable();
            $table->string('threshold_treatment', 40);
            $table->text('required_supporting_data')->nullable();
            $table->text('exemption_note')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('legal_instrument', 200);
            $table->string('section_reference', 200);
            $table->string('source_url', 500);
            $table->date('publication_date')->nullable();
            $table->date('legal_effective_date')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('approval_status', 20);
            $table->unsignedBigInteger('supersedes_version_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('retired_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->unique(['rule_group_id', 'version_number'], 'tax_rule_versions_group_version_unique');
            $table->index(['tax_type', 'supply_classification', 'rule_code', 'approval_status'], 'tax_rule_versions_scope_index');
        });

        Schema::table('tax_rule_versions', function (Blueprint $table) {
            $table->foreign('supersedes_version_id', 'tax_rule_versions_supersedes_foreign')
                ->references('id')
                ->on('tax_rule_versions')
                ->restrictOnDelete();
        });

        Schema::create('tax_rule_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_rule_version_id')->constrained('tax_rule_versions')->restrictOnDelete();
            $table->string('event', 40);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tax_rule_version_id', 'id'], 'tax_rule_events_version_index');
        });

        Schema::create('mushak63_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commercial_invoice_id')->constrained('commercial_invoices')->restrictOnDelete();
            $table->string('status', 20);
            $table->string('supply_classification', 160);
            $table->string('rule_code', 80)->default('');
            $table->foreignId('pinned_tax_rule_version_id')->nullable()->constrained('tax_rule_versions')->restrictOnDelete();
            $table->json('pinned_rule_snapshot')->nullable();
            $table->string('party_legal_name', 200);
            $table->string('party_trading_name', 200)->nullable();
            $table->string('party_tin', 30)->nullable();
            $table->string('party_bin', 30)->nullable();
            $table->string('party_vat_registration_number', 40)->nullable();
            $table->string('quotation_number', 32)->nullable();
            $table->string('invoice_number', 32)->nullable();
            $table->json('challan_numbers')->nullable();
            $table->decimal('invoice_total', 18, 2);
            $table->char('currency_code', 3)->default('BDT');
            $table->json('company_snapshot')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['commercial_invoice_id', 'status'], 'mushak63_documents_invoice_status_index');
        });

        Schema::create('mushak63_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mushak63_document_id')->constrained('mushak63_documents')->restrictOnDelete();
            $table->foreignId('commercial_invoice_line_id')->constrained('commercial_invoice_lines')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->text('description');
            $table->string('unit', 40);
            $table->decimal('quantity', 16, 4);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('line_total', 18, 2);

            $table->unique(['mushak63_document_id', 'position'], 'mushak63_lines_position_unique');
        });

        Schema::create('mushak63_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mushak63_document_id')->constrained('mushak63_documents')->restrictOnDelete();
            $table->string('event', 40);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['mushak63_document_id', 'id'], 'mushak63_events_document_index');
        });

        foreach (['vds' => 'vds_records', 'ait' => 'ait_records'] as $prefix => $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->string('direction', 32);
                $table->foreignId('party_id')->constrained('parties')->restrictOnDelete();
                $table->foreignId('commercial_invoice_id')->nullable()->constrained('commercial_invoices')->restrictOnDelete();
                $table->string('underlying_reference', 200)->nullable();
                $table->string('supply_classification', 160);
                $table->string('rule_code', 80)->default('');
                $table->decimal('basis_amount', 18, 2);
                $table->char('currency_code', 3)->default('BDT');
                $table->date('record_date');
                $table->foreignId('pinned_tax_rule_version_id')->nullable()->constrained('tax_rule_versions')->restrictOnDelete();
                $table->json('pinned_rule_snapshot')->nullable();
                $table->string('certificate_reference', 120)->nullable();
                $table->string('treasury_reference', 120)->nullable();
                $table->string('party_legal_name', 200);
                $table->string('party_tin', 30)->nullable();
                $table->string('party_bin', 30)->nullable();
                $table->json('company_snapshot')->nullable();
                $table->text('internal_notes')->nullable();
                $table->string('status', 20);
                $table->timestamp('voided_at')->nullable();
                $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('void_reason')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['direction', 'status'], $table->getTable().'_direction_status_index');
            });

            Schema::create($prefix.'_record_events', function (Blueprint $table) use ($prefix, $tableName): void {
                $table->id();
                $table->foreignId($prefix.'_record_id')->constrained($tableName)->restrictOnDelete();
                $table->string('event', 40);
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('remarks')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ait_record_events');
        Schema::dropIfExists('ait_records');
        Schema::dropIfExists('vds_record_events');
        Schema::dropIfExists('vds_records');
        Schema::dropIfExists('mushak63_events');
        Schema::dropIfExists('mushak63_lines');
        Schema::dropIfExists('mushak63_documents');
        Schema::dropIfExists('tax_rule_events');
        Schema::dropIfExists('tax_rule_versions');
    }
};

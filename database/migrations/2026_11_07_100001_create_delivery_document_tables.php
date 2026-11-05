<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_challan_number_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamp('updated_at')->nullable();
            $table->primary('year');
        });

        Schema::create('commercial_invoice_number_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamp('updated_at')->nullable();
            $table->primary('year');
        });

        Schema::create('delivery_challans', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->nullable();
            $table->foreignId('quotation_id')->constrained('quotations')->restrictOnDelete();
            $table->foreignId('quotation_revision_id')->constrained('quotation_revisions')->restrictOnDelete();
            $table->string('status', 20);
            $table->date('challan_date');
            $table->string('delivery_location', 200)->nullable();
            $table->string('recipient_name', 160)->nullable();
            $table->string('recipient_phone', 40)->nullable();
            $table->string('vehicle_or_carrier', 160)->nullable();
            $table->string('carrier_reference', 120)->nullable();
            $table->string('party_legal_name', 200);
            $table->string('party_trading_name', 200)->nullable();
            $table->string('party_kind', 40);
            $table->string('party_tin', 30)->nullable();
            $table->string('party_bin', 30)->nullable();
            $table->string('party_vat_registration_number', 40)->nullable();
            $table->string('party_email')->nullable();
            $table->string('party_phone', 40)->nullable();
            $table->string('contact_name', 160)->nullable();
            $table->string('contact_designation', 120)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->json('billing_address')->nullable();
            $table->json('shipping_address')->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->json('company_snapshot')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('acknowledgment_name', 160)->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('number', 'delivery_challans_number_unique');
            $table->index(['quotation_id', 'status'], 'delivery_challans_quotation_status_index');
        });

        Schema::create('delivery_challan_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_challan_id')->constrained('delivery_challans')->restrictOnDelete();
            $table->foreignId('quotation_line_id')->constrained('quotation_lines')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->text('description');
            $table->string('unit', 40);
            $table->decimal('quantity', 16, 4);
            $table->decimal('accepted_quantity', 16, 4);

            $table->unique(['delivery_challan_id', 'position'], 'delivery_challan_lines_challan_position_unique');
            $table->unique(['delivery_challan_id', 'quotation_line_id'], 'delivery_challan_lines_challan_line_unique');
        });

        Schema::create('delivery_challan_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_challan_id')->constrained('delivery_challans')->restrictOnDelete();
            $table->string('event', 40);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_name', 160)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['delivery_challan_id', 'id'], 'delivery_challan_events_challan_id_index');
        });

        Schema::create('commercial_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->nullable();
            $table->foreignId('quotation_id')->constrained('quotations')->restrictOnDelete();
            $table->foreignId('quotation_revision_id')->constrained('quotation_revisions')->restrictOnDelete();
            $table->string('status', 20);
            $table->date('invoice_date');
            $table->string('party_legal_name', 200);
            $table->string('party_trading_name', 200)->nullable();
            $table->string('party_kind', 40);
            $table->string('party_tin', 30)->nullable();
            $table->string('party_bin', 30)->nullable();
            $table->string('party_vat_registration_number', 40)->nullable();
            $table->string('party_email')->nullable();
            $table->string('party_phone', 40)->nullable();
            $table->string('contact_name', 160)->nullable();
            $table->string('contact_designation', 120)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->json('billing_address')->nullable();
            $table->json('shipping_address')->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('tax_notice');
            $table->string('tax_treatment', 40);
            $table->decimal('subtotal', 18, 2);
            $table->decimal('total', 18, 2);
            $table->char('currency_code', 3)->default('BDT');
            $table->json('company_snapshot')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('number', 'commercial_invoices_number_unique');
            $table->index(['quotation_id', 'status'], 'commercial_invoices_quotation_status_index');
        });

        Schema::create('commercial_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commercial_invoice_id')->constrained('commercial_invoices')->restrictOnDelete();
            $table->foreignId('delivery_challan_line_id')->constrained('delivery_challan_lines')->restrictOnDelete();
            $table->foreignId('quotation_line_id')->constrained('quotation_lines')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->text('description');
            $table->string('unit', 40);
            $table->decimal('quantity', 16, 4);
            $table->decimal('unit_price', 16, 2);
            $table->decimal('line_total', 18, 2);

            $table->unique(['commercial_invoice_id', 'position'], 'commercial_invoice_lines_invoice_position_unique');
            $table->unique(['commercial_invoice_id', 'delivery_challan_line_id'], 'commercial_invoice_lines_invoice_challan_unique');
        });

        Schema::create('commercial_invoice_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commercial_invoice_id')->constrained('commercial_invoices')->restrictOnDelete();
            $table->string('event', 40);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['commercial_invoice_id', 'id'], 'commercial_invoice_events_invoice_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_invoice_events');
        Schema::dropIfExists('commercial_invoice_lines');
        Schema::dropIfExists('commercial_invoices');
        Schema::dropIfExists('delivery_challan_events');
        Schema::dropIfExists('delivery_challan_lines');
        Schema::dropIfExists('delivery_challans');
        Schema::dropIfExists('commercial_invoice_number_sequences');
        Schema::dropIfExists('delivery_challan_number_sequences');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_number_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamp('updated_at')->nullable();
            $table->primary('year');
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32);
            $table->foreignId('party_id')->constrained('parties')->restrictOnDelete();
            $table->string('party_role', 20);
            $table->unsignedBigInteger('current_revision_id')->nullable();
            $table->string('status', 20);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('number', 'quotations_number_unique');
            $table->index('current_revision_id', 'quotations_current_revision_id_index');
            $table->index(['status', 'id'], 'quotations_status_id_index');
            $table->index('created_at', 'quotations_created_at_index');
        });

        Schema::create('quotation_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->restrictOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('status', 20);
            $table->date('quotation_date');
            $table->date('valid_until');
            $table->char('currency_code', 3)->default('BDT');
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
            $table->text('terms')->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('tax_notice');
            $table->string('tax_treatment', 40);
            $table->decimal('subtotal', 18, 2);
            $table->decimal('total', 18, 2);
            $table->text('cancel_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expired_at')->nullable();
            $table->foreignId('expired_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['quotation_id', 'revision_number'], 'quotation_revisions_quotation_revision_unique');
            $table->index('quotation_date', 'quotation_revisions_quotation_date_index');
            $table->index(['quotation_id', 'status'], 'quotation_revisions_quotation_status_index');
        });

        Schema::create('quotation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_revision_id')->constrained('quotation_revisions')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->text('description');
            $table->string('unit', 40);
            $table->decimal('quantity', 16, 4);
            $table->decimal('unit_price', 16, 2);
            $table->decimal('line_total', 18, 2);

            $table->unique(['quotation_revision_id', 'position'], 'quotation_lines_revision_position_unique');
        });

        Schema::create('quotation_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_revision_id')->constrained('quotation_revisions')->restrictOnDelete();
            $table->string('action', 20);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['quotation_revision_id', 'id'], 'quotation_approvals_revision_id_index');
        });

        Schema::create('quotation_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_revision_id')->constrained('quotation_revisions')->restrictOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_label', 200)->nullable();
            $table->string('delivery_status', 40);
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('quotation_share_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_revision_id')->constrained('quotation_revisions')->restrictOnDelete();
            $table->char('token_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique('token_hash', 'quotation_share_tokens_token_hash_unique');
            $table->index(['quotation_revision_id', 'revoked_at'], 'quotation_share_tokens_revision_revoked_index');
        });

        Schema::create('quotation_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_revision_id')->constrained('quotation_revisions')->restrictOnDelete();
            $table->string('decision', 20);
            $table->string('source', 20);
            $table->string('respondent_name', 160);
            $table->string('respondent_designation', 120)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('quotation_share_token_id')->nullable()->constrained('quotation_share_tokens')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique('quotation_revision_id', 'quotation_responses_revision_unique');
        });

        Schema::create('quotation_share_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_share_token_id')->nullable()->constrained('quotation_share_tokens')->nullOnDelete();
            $table->string('event', 40);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['quotation_share_token_id', 'id'], 'quotation_share_access_logs_token_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_share_access_logs');
        Schema::dropIfExists('quotation_responses');
        Schema::dropIfExists('quotation_share_tokens');
        Schema::dropIfExists('quotation_sends');
        Schema::dropIfExists('quotation_approvals');
        Schema::dropIfExists('quotation_lines');
        Schema::dropIfExists('quotation_revisions');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('quotation_number_sequences');
    }
};

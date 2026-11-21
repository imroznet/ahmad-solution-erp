<?php

use App\Accounting\AccountingInstaller;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_number_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamp('updated_at')->nullable();
            $table->primary('year');
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32);
            $table->foreignId('party_id')->constrained('parties')->restrictOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->date('receipt_date');
            $table->decimal('amount_received', 18, 2)->default('0.00');
            $table->decimal('vds_deducted', 18, 2)->default('0.00');
            $table->decimal('ait_deducted', 18, 2)->default('0.00');
            $table->string('mode', 20);
            $table->string('reference', 80)->nullable();
            $table->string('status', 20);
            $table->text('void_reason')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('number');
            $table->index(['status', 'receipt_date']);
            $table->index('party_id');
        });

        Schema::create('receipt_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('receipts')->restrictOnDelete();
            $table->foreignId('commercial_invoice_id')->constrained('commercial_invoices')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestamps();

            $table->unique(['receipt_id', 'commercial_invoice_id']);
            $table->index('commercial_invoice_id');
        });

        app(AccountingInstaller::class)->install();
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_allocations');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('receipt_number_sequences');
    }
};

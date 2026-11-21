<?php

use App\Accounting\AccountChart;
use App\Accounting\AccountingInstaller;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('receipts', 'cash_flow_category')) {
            Schema::table('receipts', function (Blueprint $table) {
                $table->string('cash_flow_category', 20)->default('operating')->after('reference');
            });
        }

        if (! Schema::hasColumn('financial_years', 'closed_at')) {
            Schema::table('financial_years', function (Blueprint $table) {
                $table->timestamp('closed_at')->nullable()->after('ends_on');
                $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
                $table->text('close_reason')->nullable()->after('closed_by');
                $table->foreignId('carry_forward_journal_id')->nullable()->after('close_reason')->constrained('journal_entries')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('payment_number_sequences')) {
            Schema::create('payment_number_sequences', function (Blueprint $table) {
                $table->unsignedSmallInteger('year');
                $table->unsignedInteger('last_number')->default(0);
                $table->timestamp('updated_at')->nullable();
                $table->primary('year');
            });
        }

        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->string('number', 32);
                $table->foreignId('party_id')->constrained('parties')->restrictOnDelete();
                $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
                $table->date('payment_date');
                $table->decimal('amount', 18, 2);
                $table->string('mode', 20);
                $table->string('reference', 80)->nullable();
                $table->string('cash_flow_category', 20)->default('operating');
                $table->string('status', 20);
                $table->text('void_reason')->nullable();
                $table->timestamp('voided_at')->nullable();
                $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique('number');
                $table->index(['status', 'payment_date']);
                $table->index('party_id');
            });
        }

        if (! Schema::hasTable('payment_allocations')) {
            Schema::create('payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
                $table->foreignId('journal_id')->constrained('journal_entries')->restrictOnDelete();
                $table->decimal('amount', 18, 2);
                $table->timestamps();

                $table->unique(['payment_id', 'journal_id']);
                $table->index('journal_id');
            });
        }

        app(AccountChart::class)->install();
        app(AccountingInstaller::class)->install();
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_number_sequences');

        if (Schema::hasColumn('financial_years', 'carry_forward_journal_id')) {
            Schema::table('financial_years', function (Blueprint $table) {
                $table->dropConstrainedForeignId('carry_forward_journal_id');
                $table->dropConstrainedForeignId('closed_by');
                $table->dropColumn(['closed_at', 'close_reason']);
            });
        }

        if (Schema::hasColumn('receipts', 'cash_flow_category')) {
            Schema::table('receipts', function (Blueprint $table) {
                $table->dropColumn('cash_flow_category');
            });
        }
    }
};

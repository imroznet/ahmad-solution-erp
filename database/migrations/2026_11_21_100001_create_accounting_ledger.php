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
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8);
            $table->string('name', 160);
            $table->string('type', 20);
            $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->restrictOnDelete();
            $table->string('system_key', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('code');
            $table->unique('system_key');
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->string('bank_name', 160);
            $table->string('branch', 160);
            $table->string('account_title', 160);
            $table->string('account_number', 40);
            $table->string('account_type', 20);
            $table->date('opened_on');
            $table->timestamps();

            $table->unique('account_id');
            $table->unique(['bank_name', 'account_number']);
        });

        Schema::create('mfs_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->string('provider', 20);
            $table->string('mobile_number', 20);
            $table->string('account_title', 160);
            $table->date('opened_on');
            $table->timestamps();

            $table->unique('account_id');
            $table->unique('mobile_number');
        });

        Schema::create('financial_years', function (Blueprint $table) {
            $table->id();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();

            $table->index(['starts_on', 'ends_on']);
        });

        Schema::create('journal_number_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamp('updated_at')->nullable();
            $table->primary('year');
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32);
            $table->string('status', 20);
            $table->date('journal_date');
            $table->date('financial_year_starts_on');
            $table->date('financial_year_ends_on');
            $table->text('memo')->nullable();
            $table->string('source', 20);
            $table->string('source_type', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('reverses_journal_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('number');
            $table->index(['status', 'journal_date']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->decimal('debit', 18, 2)->default('0.00');
            $table->decimal('credit', 18, 2)->default('0.00');
            $table->string('memo', 200)->nullable();
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->index(['account_id', 'journal_id']);
        });

        Schema::create('account_openings', function (Blueprint $table) {
            $table->foreignId('account_id')->primary()->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('journal_id')->constrained('journal_entries')->restrictOnDelete();
        });

        app(AccountChart::class)->install();
        app(AccountingInstaller::class)->install();
    }

    public function down(): void
    {
        Schema::dropIfExists('account_openings');
        Schema::dropIfExists('mfs_accounts');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('journal_number_sequences');
        Schema::dropIfExists('financial_years');
        Schema::dropIfExists('chart_of_accounts');
    }
};

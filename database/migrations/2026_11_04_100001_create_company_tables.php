<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('singleton_key', 20)->default('primary')->unique();
            $table->string('legal_name', 200);
            $table->string('trading_name', 200)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('rjsc_registration_number', 60)->nullable();
            $table->date('incorporation_date')->nullable();
            $table->string('business_type', 120)->nullable();
            $table->string('tin', 30)->nullable();
            $table->string('bin', 30)->nullable();
            $table->string('trade_licence_number', 60)->nullable();
            $table->string('trade_licence_issuing_authority', 160)->nullable();
            $table->date('trade_licence_issued_on')->nullable();
            $table->date('trade_licence_expires_on')->nullable();
            $table->date('financial_year_starts_on');
            $table->date('financial_year_ends_on');
            $table->char('currency_code', 3)->default('BDT');
            $table->string('website', 200)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('company_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_profile_id')->constrained('company_profiles')->restrictOnDelete();
            $table->string('address_type', 40);
            $table->string('line_1', 200);
            $table->string('line_2', 200)->nullable();
            $table->string('area', 120)->nullable();
            $table->string('upazila', 120)->nullable();
            $table->string('district', 120);
            $table->string('division', 120)->nullable();
            $table->string('postcode', 20)->nullable();
            $table->string('country', 80);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->boolean('is_default_correspondence')->default(false);
            $table->boolean('is_default_billing')->default(false);
            $table->text('remarks')->nullable();
            $table->foreignId('supersedes_address_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_profile_id', 'address_type', 'is_active'], 'company_addresses_type_active_index');
            $table->foreign('supersedes_address_id')
                ->references('id')
                ->on('company_addresses')
                ->nullOnDelete();
        });

        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('company_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_profile_id')->constrained('company_profiles')->restrictOnDelete();
            $table->foreignId('document_category_id')->constrained('document_categories')->restrictOnDelete();
            $table->string('title', 200);
            $table->string('document_number', 80)->nullable();
            $table->string('issuing_authority', 160)->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable()->index();
            $table->string('verification_status', 20)->default('pending')->index();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_profile_id', 'document_category_id'], 'company_documents_profile_category_index');
        });

        Schema::create('company_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_document_id')->constrained('company_documents')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('storage_path')->unique();
            $table->string('original_filename', 180);
            $table->string('mime_type', 120);
            $table->string('extension', 8);
            $table->unsignedBigInteger('size_bytes');
            $table->string('note', 500)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_document_id', 'version_number'], 'company_document_versions_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_document_versions');
        Schema::dropIfExists('company_documents');
        Schema::dropIfExists('document_categories');
        Schema::dropIfExists('company_addresses');
        Schema::dropIfExists('company_profiles');
    }
};

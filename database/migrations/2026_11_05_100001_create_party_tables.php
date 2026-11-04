<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name', 200);
            $table->string('trading_name', 200)->nullable();
            $table->string('legal_name_bn', 200)->nullable();
            $table->string('trading_name_bn', 200)->nullable();
            $table->string('party_kind', 40);
            $table->string('registration_number', 60)->nullable();
            $table->string('tin', 30)->nullable();
            $table->string('bin', 30)->nullable();
            $table->string('vat_registration_number', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('website', 200)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('active');
            $table->char('currency_code', 3)->default('BDT');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('legal_name', 'parties_legal_name_index');
            $table->index('trading_name', 'parties_trading_name_index');
            $table->index('tin', 'parties_tin_index');
            $table->index('bin', 'parties_bin_index');
            $table->index('email', 'parties_email_index');
            $table->index('phone', 'parties_phone_index');
            $table->index(['status', 'legal_name'], 'parties_status_legal_name_index');
        });

        Schema::create('party_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained('parties')->restrictOnDelete();
            $table->string('role', 20);
            $table->timestamp('created_at')->nullable();

            $table->unique(['party_id', 'role'], 'party_roles_party_id_role_unique');
            $table->index('role', 'party_roles_role_index');
        });

        Schema::create('party_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained('parties')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('designation', 120)->nullable();
            $table->string('department', 120)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('alternative_phone', 40)->nullable();
            $table->string('whatsapp_number', 40)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['party_id', 'is_primary', 'is_active'], 'party_contacts_primary_index');
            $table->index('email', 'party_contacts_email_index');
            $table->index('phone', 'party_contacts_phone_index');
        });

        Schema::create('party_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained('parties')->restrictOnDelete();
            $table->string('address_type', 40);
            $table->string('address_line_1', 200);
            $table->string('address_line_2', 200)->nullable();
            $table->string('area', 120)->nullable();
            $table->string('upazila', 120)->nullable();
            $table->string('district', 120);
            $table->string('division', 120)->nullable();
            $table->string('postcode', 20)->nullable();
            $table->string('country', 80);
            $table->boolean('is_default_billing')->default(false);
            $table->boolean('is_default_shipping')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->foreignId('supersedes_address_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['party_id', 'is_active'], 'party_addresses_party_active_index');
            $table->index('district', 'party_addresses_district_index');
            $table->index('address_type', 'party_addresses_address_type_index');
            $table->foreign('supersedes_address_id')
                ->references('id')
                ->on('party_addresses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_addresses');
        Schema::dropIfExists('party_contacts');
        Schema::dropIfExists('party_roles');
        Schema::dropIfExists('parties');
    }
};

<?php

namespace Tests\Feature;

use App\Company\CompanyModuleInstaller;
use App\Company\CompanyModuleSql;
use App\Company\DocumentExpiry;
use App\Models\AuditLog;
use App\Models\CompanyAddress;
use App\Models\CompanyDocument;
use App\Models\CompanyDocumentVersion;
use App\Models\CompanyProfile;
use App\Models\DocumentCategory;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_is_private_and_updates_are_audited(): void
    {
        $sales = $this->userWithRole('sales-executive');
        $documents = $this->userWithRole('document-manager');
        $admin = $this->userWithRole('administrator');

        $this->actingAs($sales)->get('/company')->assertOk()->assertSee('No company profile has been saved.');
        $this->actingAs($sales)->get('/company/profile/edit')->assertForbidden();
        $this->actingAs($documents)->put('/company/profile', $this->profilePayload())->assertForbidden();

        $this->actingAs($admin)->put('/company/profile', $this->profilePayload([
            'currency_code' => 'USD',
        ]))->assertSessionHasErrors('currency_code');

        $this->actingAs($admin)->put('/company/profile', $this->profilePayload([
            'financial_year_ends_on' => '2027-07-02',
        ]))->assertSessionHasErrors('financial_year_ends_on');

        $this->actingAs($admin)->put('/company/profile', $this->profilePayload())
            ->assertRedirect(route('company.show'));

        $this->assertSame(1, CompanyProfile::query()->count());
        $this->assertDatabaseHas('company_profiles', [
            'legal_name' => 'Ahmad Solution Ltd.',
            'email' => 'office@ahmad.example',
            'currency_code' => 'BDT',
            'tin' => '123456789012',
        ]);

        $this->actingAs($admin)->put('/company/profile', $this->profilePayload([
            'trading_name' => 'Ahmad Solution',
        ]))->assertRedirect(route('company.show'));

        $this->assertSame(1, CompanyProfile::query()->count());
        $encoded = AuditLog::query()->where('event', 'company_profile_updated')->get()->toJson();
        $this->assertStringContainsString('Ahmad Solution Ltd.', $encoded);
        $this->assertStringNotContainsString('logo_path', $encoded);

        $this->actingAs($sales)->get('/company')->assertSee('Ahmad Solution Ltd.')->assertSee('123456789012');
        $this->post('/logout');
        $this->get('/company')->assertRedirect(route('login'));
    }

    public function test_logo_stays_on_the_private_disk(): void
    {
        Storage::fake('local');
        $admin = $this->userWithRole('administrator');
        $viewer = $this->userWithRole('viewer');

        $this->actingAs($admin)->put('/company/profile', $this->profilePayload([
            'logo' => UploadedFile::fake()->image('mark.png', 40, 40),
        ]))->assertRedirect(route('company.show'));

        $path = CompanyProfile::query()->firstOrFail()->logo_path;
        $this->assertIsString($path);
        $this->assertMatchesRegularExpression('#^company-logo/[0-9a-f-]{36}\.png$#', $path);
        Storage::disk('local')->assertExists($path);
        $this->assertFileDoesNotExist(public_path($path));

        $this->actingAs($admin)->get(route('company.logo'))->assertOk();
        $this->actingAs($viewer)->get(route('company.logo'))->assertForbidden();
        $this->get('/storage/'.$path)->assertNotFound();
    }

    public function test_registered_and_corporate_addresses_stay_separate(): void
    {
        $admin = $this->userWithRole('administrator');
        $this->actingAs($admin)->put('/company/profile', $this->profilePayload())->assertRedirect();

        $this->actingAs($admin)->post('/company/addresses', $this->addressPayload([
            'is_default_correspondence' => '1',
        ]))->assertRedirect(route('company.addresses.index'));

        $this->actingAs($admin)->post('/company/addresses', $this->addressPayload([
            'address_type' => 'corporate_office',
            'line_1' => '88 Gulshan Avenue',
            'area' => 'Gulshan',
            'is_default_billing' => '1',
        ]))->assertRedirect(route('company.addresses.index'));

        $this->actingAs($admin)->post('/company/addresses', $this->addressPayload([
            'line_1' => 'Another registered office',
        ]))->assertSessionHasErrors('address_type');

        $registered = CompanyAddress::query()->where('address_type', 'registered_office')->firstOrFail();
        $corporate = CompanyAddress::query()->where('address_type', 'corporate_office')->firstOrFail();
        $this->assertNotSame($registered->line_1, $corporate->line_1);
        $this->assertTrue($registered->is_default_correspondence);
        $this->assertFalse($registered->is_default_billing);
        $this->assertTrue($corporate->is_default_billing);

        $this->actingAs($admin)->post('/company/addresses', $this->addressPayload([
            'address_type' => 'branch_office',
            'line_1' => 'Branch lane',
            'is_default_correspondence' => '1',
        ]))->assertRedirect(route('company.addresses.index'));

        $this->assertFalse($registered->fresh()->is_default_correspondence);
        $this->assertTrue(
            CompanyAddress::query()->where('line_1', 'Branch lane')->firstOrFail()->is_default_correspondence
        );
        $this->assertSame(3, CompanyAddress::query()->count());
    }

    public function test_closing_or_replacing_an_address_keeps_the_old_row(): void
    {
        $admin = $this->userWithRole('administrator');
        $this->actingAs($admin)->put('/company/profile', $this->profilePayload())->assertRedirect();
        $this->actingAs($admin)->post('/company/addresses', $this->addressPayload())->assertRedirect();
        $original = CompanyAddress::query()->firstOrFail();

        $this->actingAs($admin)->post(route('company.addresses.close', $original), [])
            ->assertSessionHasErrors('confirm_close');
        $this->assertTrue($original->fresh()->is_active);

        $this->actingAs($admin)->post('/company/addresses', $this->addressPayload([
            'line_1' => 'New registered premises',
            'supersedes_address_id' => $original->id,
        ]))->assertRedirect(route('company.addresses.index'));

        $this->assertFalse($original->fresh()->is_active);
        $this->assertSame('12 Green Road', $original->fresh()->line_1);
        $replacement = CompanyAddress::query()->where('line_1', 'New registered premises')->firstOrFail();
        $this->assertTrue($replacement->is_active);
        $this->assertSame($original->id, $replacement->supersedes_address_id);
        $this->assertDatabaseHas('audit_logs', ['event' => 'company_address_closed']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'company_address_created']);
    }

    public function test_document_files_are_private_versioned_and_permission_checked(): void
    {
        Storage::fake('local');
        $manager = $this->userWithRole('document-manager');
        $viewer = $this->userWithRole('viewer');
        $view = Permission::query()->where('slug', 'company.documents.view')->firstOrFail();
        $viewer->roles()->firstOrFail()->permissions()->syncWithoutDetaching([$view->id]);
        $viewer->unsetRelation('roles');

        $this->actingAs($manager)->put('/company/profile', $this->profilePayload())->assertForbidden();
        $admin = $this->userWithRole('administrator');
        $this->actingAs($admin)->put('/company/profile', $this->profilePayload())->assertRedirect();

        $category = DocumentCategory::query()->where('slug', 'trade-licence')->firstOrFail();

        $this->actingAs($viewer)->get('/company/documents/create')->assertForbidden();
        $this->actingAs($manager)->post('/company/documents', [
            'title' => 'Trade licence 2026',
            'document_category_id' => $category->id,
            'document_number' => 'TL-88',
            'issuing_authority' => 'Dhaka South City Corporation',
            'issued_on' => '2026-07-01',
            'expires_on' => now()->timezone('Asia/Dhaka')->addDays(10)->toDateString(),
            'verification_status' => 'pending',
            'files' => [UploadedFile::fake()->create('notes.txt', 12, 'text/plain')],
        ])->assertSessionHasErrors('files.0');

        $this->actingAs($manager)->post('/company/documents', [
            'title' => 'Trade licence 2026',
            'document_category_id' => $category->id,
            'verification_status' => 'pending',
            'files' => [UploadedFile::fake()->create('shell.php', 12, 'application/x-php')],
        ])->assertSessionHasErrors('files.0');

        $this->actingAs($manager)->post('/company/documents', [
            'title' => 'Trade licence 2026',
            'document_category_id' => $category->id,
            'verification_status' => 'verified',
            'document_number' => 'TL-88',
            'issued_on' => '2026-07-01',
            'expires_on' => now()->timezone('Asia/Dhaka')->addDays(10)->toDateString(),
            'files' => [
                UploadedFile::fake()->create('licence.pdf', 20, 'application/pdf'),
                UploadedFile::fake()->create('shell.php.pdf', 20, 'application/pdf'),
            ],
        ])->assertSessionHasErrors();

        $this->assertSame(0, CompanyDocument::query()->count());

        $this->actingAs($manager)->post('/company/documents', [
            'title' => 'Trade licence 2026',
            'document_category_id' => $category->id,
            'verification_status' => 'verified',
            'document_number' => 'TL-88',
            'issued_on' => '2026-07-01',
            'expires_on' => now()->timezone('Asia/Dhaka')->addDays(10)->toDateString(),
            'files' => [UploadedFile::fake()->create('licence.pdf', 20, 'application/pdf')],
        ])->assertRedirect();

        $document = CompanyDocument::query()->firstOrFail();
        $first = $document->versions()->firstOrFail();
        $this->assertSame(1, $first->version_number);
        $this->assertMatchesRegularExpression('#^company-documents/'.$document->id.'/[0-9a-f-]{36}\.pdf$#', $first->storage_path);
        Storage::disk('local')->assertExists($first->storage_path);
        $this->assertFileDoesNotExist(public_path($first->storage_path));

        $this->actingAs($manager)->post(route('company.documents.versions.store', $document), [
            'files' => [UploadedFile::fake()->image('scan.jpg')],
            'note' => 'Scanned copy',
        ])->assertRedirect(route('company.documents.show', $document));

        $this->assertSame(2, $document->versions()->count());
        Storage::disk('local')->assertExists($first->fresh()->storage_path);
        $second = CompanyDocumentVersion::query()->where('version_number', 2)->firstOrFail();
        $this->assertNotSame($first->storage_path, $second->storage_path);

        $this->post('/logout');
        $this->get(route('company.documents.download', [$document, $first]))->assertRedirect(route('login'));
        $this->actingAs($viewer)->get(route('company.documents.show', $document))->assertOk();
        $this->actingAs($viewer)->get(route('company.documents.download', [$document, $first]))->assertForbidden();
        $this->get('/storage/'.$first->storage_path)->assertNotFound();

        $this->actingAs($manager)->get(route('company.documents.download', [$document, $first]))->assertOk();
        $audit = AuditLog::query()->where('event', 'company_document_downloaded')->firstOrFail();
        $this->assertSame($first->original_filename, $audit->new_values['original_filename'] ?? null);
        $this->assertStringNotContainsString($first->storage_path, $audit->toJson());

        $this->actingAs($manager)->put(route('company.documents.update', $document), [
            'title' => 'Trade licence renewed',
            'document_category_id' => $category->id,
            'verification_status' => 'verified',
            'document_number' => 'TL-88',
        ])->assertRedirect();

        $this->assertSame(2, $document->versions()->count());
        $this->assertSame('Trade licence renewed', $document->fresh()->title);

        $this->actingAs($manager)->post(route('company.documents.destroy', $document), [])
            ->assertSessionHasErrors('confirm_delete');
        $this->actingAs($manager)->post(route('company.documents.destroy', $document), [
            'confirm_delete' => '1',
        ])->assertRedirect(route('company.documents.index'));

        $this->assertSoftDeleted($document);
        Storage::disk('local')->assertExists($first->storage_path);
        $this->actingAs($manager)->get(route('company.documents.download', [$document, $first]))->assertNotFound();
    }

    public function test_expiry_windows_use_dhaka_dates_and_the_dashboard(): void
    {
        $admin = $this->userWithRole('administrator');
        $viewer = $this->userWithRole('viewer');
        $this->actingAs($admin)->put('/company/profile', $this->profilePayload())->assertRedirect();
        $profile = CompanyProfile::query()->firstOrFail();
        $category = DocumentCategory::query()->where('slug', 'insurance-certificate')->firstOrFail();
        $today = now()->timezone('Asia/Dhaka')->startOfDay();

        $this->makeDocument($profile->id, $category->id, $admin->id, 'Expired cover', $today->copy()->subDay()->toDateString());
        $this->makeDocument($profile->id, $category->id, $admin->id, 'Due this week', $today->copy()->addDays(7)->toDateString());
        $this->makeDocument($profile->id, $category->id, $admin->id, 'Due next month', $today->copy()->addDays(20)->toDateString());
        $this->makeDocument($profile->id, $category->id, $admin->id, 'No date', null);

        $counts = app(DocumentExpiry::class)->counts();
        $this->assertSame(1, $counts['expired']);
        $this->assertSame(1, $counts['within_7']);
        $this->assertSame(1, $counts['within_15']);
        $this->assertSame(2, $counts['within_30']);
        $this->assertSame(2, $counts['within_60']);

        $this->actingAs($viewer)->get('/')->assertOk()->assertDontSee('Within 7 days');
        $this->actingAs($admin)->get('/')->assertOk()->assertSee('Within 7 days');
        $this->actingAs($admin)->get(route('company.documents.expiring', ['window' => 'expired']))
            ->assertOk()
            ->assertSee('Expired cover')
            ->assertDontSee('Due this week');
        $this->actingAs($admin)->get(route('company.documents.expiring', ['window' => '7']))
            ->assertOk()
            ->assertSee('Due this week')
            ->assertDontSee('Due next month')
            ->assertDontSee('No date');
    }

    public function test_custom_categories_can_be_renamed_without_removing_system_ones(): void
    {
        $manager = $this->userWithRole('document-manager');
        $this->assertSame(20, DocumentCategory::query()->count());

        $this->actingAs($manager)->post('/company/document-categories', [
            'name' => 'Factory licence',
        ])->assertRedirect(route('company.categories.index'));

        $custom = DocumentCategory::query()->where('name', 'Factory licence')->firstOrFail();
        $this->assertFalse($custom->is_system);

        $this->actingAs($manager)->put(route('company.categories.update', $custom), [
            'name' => 'Factory licence copy',
            'is_active' => '0',
        ])->assertRedirect(route('company.categories.index'));

        $custom->refresh();
        $this->assertSame('Factory licence copy', $custom->name);
        $this->assertFalse($custom->is_active);
        $this->assertSame('certificate-of-incorporation', DocumentCategory::query()->findOrFail(1)->slug);
        $this->assertDatabaseHas('audit_logs', ['event' => 'document_category_created']);
    }

    public function test_installer_adds_company_grants_without_restoring_removed_viewer_access(): void
    {
        $this->seedAccess();
        $viewer = Role::query()->where('slug', 'viewer')->firstOrFail();
        $audit = Permission::query()->where('slug', 'audit.view')->firstOrFail();
        $viewer->permissions()->sync([$audit->id]);

        $manager = Role::query()->where('slug', 'document-manager')->firstOrFail();
        $documents = Permission::query()->where('slug', 'company.documents.view')->firstOrFail();
        $manager->permissions()->detach($documents->id);

        app(CompanyModuleInstaller::class)->install();

        $this->assertSame(['audit.view'], $viewer->fresh()->permissions()->orderBy('slug')->pluck('slug')->all());
        $this->assertTrue($manager->fresh()->permissions->contains('slug', 'company.documents.view'));
    }

    public function test_company_sql_matches_the_committed_files(): void
    {
        $categories = file_get_contents(base_path('deploy/company-reference-data.sql'));
        $upgrade = file_get_contents(base_path('deploy/upgrade-company-module.sql'));

        $this->assertIsString($categories);
        $this->assertIsString($upgrade);
        $this->assertSame($categories, CompanyModuleSql::categories());
        $this->assertSame($upgrade, CompanyModuleSql::upgrade());
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `company_documents`', $upgrade);
        $this->assertStringContainsString('company.documents.download', $upgrade);
        $this->assertStringContainsString('NO_BACKSLASH_ESCAPES', $upgrade);
        $this->assertStringContainsString('WHERE NOT EXISTS', $upgrade);
        $this->assertStringNotContainsString('INSERT INTO `users`', $categories);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function profilePayload(array $overrides = []): array
    {
        return array_merge([
            'legal_name' => 'Ahmad Solution Ltd.',
            'trading_name' => 'ASL',
            'rjsc_registration_number' => 'C-123456',
            'incorporation_date' => '2018-03-15',
            'business_type' => 'Private company limited by shares',
            'tin' => '123456789012',
            'bin' => '000123456',
            'trade_licence_number' => 'TRAD-9',
            'trade_licence_issuing_authority' => 'Dhaka South City Corporation',
            'trade_licence_issued_on' => '2025-07-01',
            'trade_licence_expires_on' => '2026-06-30',
            'financial_year_starts_on' => '2025-07-01',
            'financial_year_ends_on' => '2026-06-30',
            'currency_code' => 'BDT',
            'website' => 'https://ahmad.example',
            'email' => 'Office@Ahmad.example',
            'phone' => '02-123456',
            'description' => 'Electrical and automation supply.',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function addressPayload(array $overrides = []): array
    {
        return array_merge([
            'address_type' => 'registered_office',
            'line_1' => '12 Green Road',
            'line_2' => '',
            'area' => 'Dhanmondi',
            'upazila' => 'Dhanmondi',
            'district' => 'Dhaka',
            'division' => 'Dhaka',
            'postcode' => '1205',
            'country' => 'Bangladesh',
            'is_active' => '1',
            'effective_from' => '2020-01-01',
            'effective_until' => '',
            'remarks' => '',
        ], $overrides);
    }

    private function makeDocument(int $profileId, int $categoryId, int $userId, string $title, ?string $expiresOn): void
    {
        CompanyDocument::query()->create([
            'company_profile_id' => $profileId,
            'document_category_id' => $categoryId,
            'title' => $title,
            'verification_status' => 'pending',
            'expires_on' => $expiresOn,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Party;
use App\Models\PartyAddress;
use App\Models\PartyContact;
use App\Models\Permission;
use App\Models\Role;
use App\Parties\PartyModuleInstaller;
use App\Parties\PartyModuleSql;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartyModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_and_unrelated_roles_cannot_open_party_pages(): void
    {
        $this->get('/customers')->assertRedirect(route('login'));

        $viewer = $this->userWithRole('viewer');
        $documents = $this->userWithRole('document-manager');

        $this->actingAs($viewer)->get('/customers')->assertForbidden();
        $this->actingAs($viewer)->get('/suppliers')->assertForbidden();
        $this->actingAs($viewer)->get('/parties')->assertForbidden();
        $this->actingAs($viewer)->get('/')->assertOk()->assertDontSee('Active customers');

        $this->actingAs($documents)->get('/customers')->assertForbidden();
        $this->actingAs($documents)->get('/suppliers')->assertForbidden();
        $this->actingAs($documents)->post('/customers', $this->partyPayload())->assertForbidden();
    }

    public function test_a_customer_can_become_a_supplier_without_a_second_party(): void
    {
        $sales = $this->userWithRole('sales-executive');
        $accounts = $this->userWithRole('accounts-manager');

        $this->actingAs($sales)->get('/customers')->assertOk()->assertSee('New customer')->assertDontSee('>Suppliers<');
        $this->actingAs($sales)->get('/suppliers')->assertForbidden();
        $this->actingAs($sales)->get('/parties')->assertForbidden();
        $this->actingAs($sales)->post('/suppliers', $this->partyPayload(['roles' => ['supplier']]))->assertForbidden();
        $this->actingAs($sales)->get('/')->assertOk()->assertSee('Active customers')->assertDontSee('Active suppliers');

        $this->actingAs($sales)->post('/customers', $this->partyPayload(['legal_name' => '']))
            ->assertSessionHasErrors('legal_name');

        $this->actingAs($sales)->post('/customers', $this->partyPayload())
            ->assertRedirect();

        $party = Party::query()->where('legal_name', 'Padma Engineering Ltd.')->firstOrFail();

        $this->assertSame('012345678901', $party->tin);
        $this->assertSame('000111222', $party->bin);
        $this->assertSame('accounts@padma.example', $party->email);
        $this->assertSame('BDT', $party->currency_code);
        $this->assertSame('পদ্মা ইঞ্জিনিয়ারিং', $party->legal_name_bn);
        $this->assertSame(1, Party::query()->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'party_created']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'party_role_added']);

        $this->actingAs($sales)->post('/customers', $this->partyPayload([
            'legal_name' => 'Different Name Ltd.',
            'roles' => ['customer', 'supplier'],
        ]))->assertForbidden();

        $this->actingAs($sales)->put(route('parties.update', $party), $this->partyPayload([
            'legal_name' => 'Padma Engineering Ltd.',
            'roles' => ['customer', 'supplier'],
            'status' => 'active',
        ]))->assertForbidden();

        $this->assertSame(['customer'], $party->fresh()?->roles()->pluck('role')->all());

        $this->actingAs($accounts)->put(route('parties.update', $party), $this->partyPayload([
            'legal_name' => 'Padma Engineering Limited',
            'roles' => ['customer', 'supplier'],
            'status' => 'active',
        ]))->assertRedirect(route('parties.show', $party));

        $this->assertSame(1, Party::query()->count());
        $this->assertEqualsCanonicalizing(['customer', 'supplier'], $party->fresh()?->roles()->pluck('role')->all());
        $this->actingAs($accounts)->get('/parties')->assertOk()->assertSee('Padma Engineering Limited')->assertSee('Customer')->assertSee('Supplier');
        $this->actingAs($accounts)->get('/customers')->assertSee('Padma Engineering Limited');
        $this->actingAs($accounts)->get('/suppliers')->assertSee('Padma Engineering Limited');
        $this->actingAs($sales)->get(route('parties.show', $party))->assertOk()->assertDontSee('>Supplier<');
        $this->actingAs($sales)->post(route('parties.contacts.store', $party), [
            'name' => 'Should Not Save',
            'is_active' => '1',
        ])->assertForbidden();

        $updated = AuditLog::query()->where('event', 'party_updated')->first();
        $this->assertNotNull($updated);
        $this->assertSame('Padma Engineering Ltd.', $updated->old_values['legal_name'] ?? null);
        $this->assertSame('Padma Engineering Limited', $updated->new_values['legal_name'] ?? null);

        $this->actingAs($accounts)->post('/suppliers', $this->partyPayload([
            'legal_name' => 'Meghna Supplies',
            'trading_name' => 'Meghna',
            'tin' => null,
            'bin' => null,
            'email' => 'buy@meghna.example',
            'phone' => '01819000002',
            'roles' => ['supplier'],
        ]))->assertRedirect();

        $this->assertNull(Party::query()->where('legal_name', 'Meghna Supplies')->firstOrFail()->tin);
        $this->actingAs($sales)->get(route('parties.show', Party::query()->where('legal_name', 'Meghna Supplies')->firstOrFail()))
            ->assertNotFound();
    }

    public function test_duplicate_warning_requires_confirmation_and_does_not_merge(): void
    {
        $accounts = $this->userWithRole('accountant');

        $this->actingAs($accounts)->post('/customers', $this->partyPayload())->assertRedirect();

        $this->actingAs($accounts)->post('/customers', $this->partyPayload([
            'legal_name' => 'Another Padma Ltd.',
            'email' => 'other@example.test',
            'phone' => '01911000009',
            'tin' => '999',
        ]))->assertSessionHasErrors('confirm_duplicate');

        $this->assertSame(1, Party::query()->count());

        $this->actingAs($accounts)->post('/customers', $this->partyPayload([
            'legal_name' => 'Another Padma Ltd.',
            'email' => 'other@example.test',
            'phone' => '01911000009',
            'tin' => '999',
            'confirm_duplicate' => '1',
        ]))->assertRedirect();

        $this->assertSame(2, Party::query()->count());
        $this->assertSame(2, Party::query()->where('bin', '000111222')->count());
    }

    public function test_one_primary_contact_and_archived_contacts_remain(): void
    {
        $sales = $this->userWithRole('sales-manager');
        $this->actingAs($sales)->post('/customers', $this->partyPayload())->assertRedirect();
        $party = Party::query()->firstOrFail();

        $this->actingAs($sales)->post(route('parties.contacts.store', $party), [
            'name' => 'Nabila Karim',
            'designation' => 'Manager',
            'department' => 'Procurement',
            'email' => 'Nabila@Padma.example',
            'phone' => '01712000001',
            'alternative_phone' => '',
            'whatsapp_number' => '01712000001',
            'is_primary' => '1',
            'is_active' => '1',
            'notes' => 'Desk contact',
        ])->assertRedirect(route('parties.show', $party));

        $first = PartyContact::query()->firstOrFail();
        $this->assertTrue($first->is_primary);
        $this->assertSame('nabila@padma.example', $first->email);

        $this->actingAs($sales)->post(route('parties.contacts.store', $party), [
            'name' => 'Inactive Lead',
            'is_primary' => '1',
            'is_active' => '0',
        ])->assertSessionHasErrors('is_primary');

        $this->actingAs($sales)->post(route('parties.contacts.store', $party), [
            'name' => 'Rahim Uddin',
            'email' => 'rahim@padma.example',
            'phone' => '01712000002',
            'is_primary' => '1',
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertFalse($first->fresh()?->is_primary);
        $this->assertTrue(PartyContact::query()->where('name', 'Rahim Uddin')->firstOrFail()->is_primary);
        $this->assertSame(1, PartyContact::query()->where('is_primary', true)->count());

        $this->actingAs($sales)->put(route('parties.contacts.update', [$party, $first]), [
            'name' => 'Nabila K.',
            'designation' => 'Manager',
            'email' => 'nabila@padma.example',
            'phone' => '01712000001',
            'is_active' => '1',
        ])->assertRedirect();

        $renamed = AuditLog::query()->where('event', 'party_contact_updated')->latest('id')->first();
        $this->assertSame('Nabila Karim', $renamed?->old_values['name'] ?? null);
        $this->assertSame('Nabila K.', $renamed?->new_values['name'] ?? null);

        $this->actingAs($sales)->post(route('parties.contacts.archive', [$party, $first]), [
            'confirm_archive' => '1',
        ])->assertRedirect();

        $this->assertFalse($first->fresh()?->is_active);
        $this->assertSame(2, PartyContact::query()->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'party_contact_archived']);

        $other = Party::query()->create([
            'legal_name' => 'Other Party',
            'party_kind' => 'individual',
            'status' => 'active',
            'currency_code' => 'BDT',
        ]);
        $other->roles()->create(['role' => 'customer']);

        $this->actingAs($sales)->put(route('parties.contacts.update', [$other, $first]))
            ->assertNotFound();
    }

    public function test_addresses_keep_history_and_one_default_for_each_purpose(): void
    {
        $sales = $this->userWithRole('sales-executive');
        $this->actingAs($sales)->post('/customers', $this->partyPayload())->assertRedirect();
        $party = Party::query()->firstOrFail();

        $this->actingAs($sales)->post(route('parties.addresses.store', $party), $this->addressPayload([
            'is_default_billing' => '1',
            'is_default_shipping' => '1',
        ]))->assertRedirect(route('parties.show', $party));

        $original = PartyAddress::query()->firstOrFail();

        $this->actingAs($sales)->post(route('parties.addresses.store', $party), $this->addressPayload([
            'address_line_1' => '44 Agrabad',
            'district' => 'Chattogram',
            'division' => 'Chattogram',
            'is_default_billing' => '1',
        ]))->assertRedirect();

        $this->assertFalse($original->fresh()?->is_default_billing);
        $this->assertTrue($original->fresh()?->is_default_shipping);
        $this->assertSame(1, PartyAddress::query()->where('is_default_billing', true)->count());

        $this->actingAs($sales)->post(route('parties.addresses.close', [$party, $original]), [
            'confirm_close' => '1',
        ])->assertRedirect();

        $this->assertFalse($original->fresh()?->is_active);
        $this->assertNotNull($original->fresh()?->effective_until);
        $this->assertDatabaseHas('audit_logs', ['event' => 'party_address_closed']);

        $replacement = PartyAddress::query()->where('district', 'Chattogram')->firstOrFail();
        $this->actingAs($sales)->post(route('parties.addresses.store', $party), $this->addressPayload([
            'address_line_1' => '44 Agrabad C/A',
            'district' => 'Chattogram',
            'division' => 'Chattogram',
            'supersedes_address_id' => $replacement->id,
            'effective_from' => '2024-02-01',
        ]))->assertRedirect();

        $this->assertFalse($replacement->fresh()?->is_active);
        $this->assertSame(3, PartyAddress::query()->count());
        $this->assertNotNull(PartyAddress::query()->where('address_line_1', '44 Agrabad C/A')->firstOrFail()->supersedes_address_id);

        $active = PartyAddress::query()->where('address_line_1', '44 Agrabad C/A')->firstOrFail();
        $this->actingAs($sales)->put(route('parties.addresses.update', [$party, $active]), $this->addressPayload([
            'address_line_1' => '44 Agrabad C/A',
            'district' => 'Sylhet',
            'division' => 'Sylhet',
            'is_default_billing' => '1',
        ]))->assertRedirect();

        $secondParty = Party::query()->create([
            'legal_name' => 'Isolated Party',
            'party_kind' => 'company',
            'status' => 'active',
            'currency_code' => 'BDT',
        ]);
        $secondParty->roles()->create(['role' => 'customer']);

        $this->actingAs($sales)->put(route('parties.addresses.update', [$secondParty, $original]), $this->addressPayload())
            ->assertNotFound();

        $this->actingAs($sales)->get('/customers?district=Sylhet')->assertOk()->assertSee('Padma Engineering Ltd.')->assertDontSee('Isolated Party');
        $this->actingAs($sales)->get('/customers?sort=district&direction=desc')->assertOk();
        $this->actingAs($sales)->get('/customers?sort=password')->assertOk()->assertSee('Padma Engineering Ltd.');
    }

    public function test_archiving_blocks_edits_until_reactivation(): void
    {
        $manager = $this->userWithRole('accounts-manager');
        $accountant = $this->userWithRole('accountant');
        $this->actingAs($manager)->post('/parties', $this->partyPayload([
            'roles' => ['customer', 'supplier'],
        ]))->assertRedirect();
        $party = Party::query()->firstOrFail();

        $this->actingAs($accountant)->post(route('parties.archive', $party), [
            'confirm_archive' => '1',
        ])->assertForbidden();

        $this->actingAs($manager)->post(route('parties.archive', $party), [
            'confirm_archive' => '1',
        ])->assertRedirect(route('parties.show', $party));

        $this->assertSame('archived', $party->fresh()?->status);
        $this->actingAs($manager)->put(route('parties.update', $party), $this->partyPayload([
            'status' => 'active',
            'roles' => ['customer', 'supplier'],
        ]))->assertForbidden();
        $this->actingAs($manager)->post(route('parties.contacts.store', $party), [
            'name' => 'Should Fail',
            'is_active' => '1',
        ])->assertForbidden();

        $this->actingAs($manager)->post(route('parties.archive', $party), [
            'confirm_reactivate' => '1',
        ])->assertRedirect();

        $this->assertSame('active', $party->fresh()?->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'party_archived']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'party_reactivated']);

        $this->actingAs($manager)->put(route('parties.update', $party), $this->partyPayload([
            'legal_name' => 'Padma Engineering Ltd.',
            'status' => 'inactive',
            'roles' => ['supplier'],
        ]))->assertRedirect();

        $this->assertSame('inactive', $party->fresh()?->status);
        $this->assertSame(['supplier'], $party->fresh()?->roles()->pluck('role')->all());
        $this->assertDatabaseHas('audit_logs', ['event' => 'party_role_removed']);
    }

    public function test_search_and_pagination_stay_in_the_database(): void
    {
        $accounts = $this->userWithRole('accounts-manager');

        foreach (range(1, 16) as $number) {
            $party = Party::query()->create([
                'legal_name' => sprintf('Relay Customer %02d', $number),
                'party_kind' => 'company',
                'status' => 'active',
                'currency_code' => 'BDT',
                'bin' => sprintf('BIN%04d', $number),
                'phone' => sprintf('017%08d', $number),
            ]);
            $party->roles()->create(['role' => 'customer']);
        }

        $this->actingAs($accounts)->get('/customers?page=2')
            ->assertOk()
            ->assertSee('Relay Customer 16')
            ->assertDontSee('Relay Customer 01');

        $this->actingAs($accounts)->get('/customers?search=BIN0016')
            ->assertOk()
            ->assertSee('Relay Customer 16')
            ->assertDontSee('Relay Customer 01');

        $this->actingAs($accounts)->get('/parties?search=%')
            ->assertOk()
            ->assertDontSee('Relay Customer 01');
    }

    public function test_installer_adds_party_grants_without_restoring_removed_access(): void
    {
        $this->seedAccess();
        $viewer = Role::query()->where('slug', 'viewer')->firstOrFail();
        $audit = Permission::query()->where('slug', 'audit.view')->firstOrFail();
        $viewer->permissions()->sync([$audit->id]);

        $sales = Role::query()->where('slug', 'sales-executive')->firstOrFail();
        $customers = Permission::query()->where('slug', 'customers.view')->firstOrFail();
        $sales->permissions()->detach($customers->id);

        app(PartyModuleInstaller::class)->install();

        $this->assertSame(['audit.view'], $viewer->fresh()?->permissions()->orderBy('slug')->pluck('slug')->all());
        $this->assertTrue($sales->fresh()?->permissions->contains('slug', 'customers.view'));
        $this->assertFalse($sales->fresh()?->permissions->contains('slug', 'suppliers.view'));
    }

    public function test_parties_upgrade_sql_matches_the_committed_file(): void
    {
        $committed = file_get_contents(base_path('deploy/upgrade-parties-module.sql'));

        $this->assertIsString($committed);
        $this->assertSame($committed, PartyModuleSql::upgrade());
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `parties`', $committed);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `party_roles`', $committed);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `party_contacts`', $committed);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `party_addresses`', $committed);
        $this->assertStringContainsString('parties.view', $committed);
        $this->assertStringContainsString('WHERE NOT EXISTS', $committed);
        $this->assertStringContainsString('NO_BACKSLASH_ESCAPES', $committed);
        $this->assertStringNotContainsString('DELETE FROM', $committed);
        $this->assertStringNotContainsString('DROP TABLE', $committed);
        $this->assertStringNotContainsString('INSERT INTO `users`', $committed);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function partyPayload(array $overrides = []): array
    {
        return array_merge([
            'legal_name' => 'Padma Engineering Ltd.',
            'trading_name' => 'Padma Eng.',
            'legal_name_bn' => 'পদ্মা ইঞ্জিনিয়ারিং',
            'trading_name_bn' => '',
            'party_kind' => 'company',
            'registration_number' => 'C-998877',
            'tin' => '012345678901',
            'bin' => '000111222',
            'vat_registration_number' => '',
            'email' => 'Accounts@Padma.example',
            'phone' => '01711000001',
            'website' => 'https://padma.example',
            'notes' => 'Synthetic test organisation.',
            'roles' => ['customer'],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function addressPayload(array $overrides = []): array
    {
        return array_merge([
            'address_type' => 'office',
            'address_line_1' => '12 Green Road',
            'address_line_2' => '',
            'area' => 'Dhanmondi',
            'upazila' => 'Dhanmondi',
            'district' => 'Dhaka',
            'division' => 'Dhaka',
            'postcode' => '1205',
            'country' => 'Bangladesh',
            'is_active' => '1',
            'effective_from' => '2020-01-01',
            'effective_until' => '',
        ], $overrides);
    }
}

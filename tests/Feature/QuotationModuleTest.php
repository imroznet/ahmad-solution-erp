<?php

namespace Tests\Feature;

use App\Models\Party;
use App\Models\Permission;
use App\Models\Quotation;
use App\Models\QuotationRevision;
use App\Models\Role;
use App\Quotations\QuotationModuleInstaller;
use App\Quotations\QuotationModuleSql;
use App\Quotations\QuotationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_and_unrelated_roles_cannot_open_quotations(): void
    {
        $this->get('/quotations')->assertRedirect(route('login'));

        $viewer = $this->userWithRole('viewer');
        $documents = $this->userWithRole('document-manager');
        $party = $this->party();
        $sales = $this->userWithRole('sales-executive');
        $this->actingAs($sales)->post('/quotations', $this->payload($party))->assertRedirect();
        $quotation = Quotation::query()->firstOrFail();

        $this->actingAs($viewer)->get('/quotations')->assertForbidden();
        $this->actingAs($documents)->get('/quotations')->assertForbidden();
        $this->actingAs($documents)->get(route('quotations.show', $quotation))->assertForbidden();
        $this->actingAs($documents)->post('/quotations', $this->payload($party))->assertForbidden();
        $this->actingAs($viewer)->get('/')->assertOk()->assertDontSee('Open quotations');
    }

    public function test_a_quotation_is_created_only_for_an_active_customer_and_keeps_its_snapshot(): void
    {
        $sales = $this->userWithRole('sales-executive');
        $supplier = $this->party('supplier', ['legal_name' => 'Only Supplier Ltd.']);
        $inactive = $this->party('customer', ['legal_name' => 'Quiet Customer', 'status' => 'inactive']);
        $customer = $this->party();
        $customer->contacts()->create([
            'name' => 'Nadia Rahman',
            'designation' => 'Manager',
            'email' => 'nadia@padma.example',
            'phone' => '01711000002',
            'is_primary' => true,
            'is_active' => true,
        ]);
        $customer->addresses()->create([
            'address_type' => 'billing',
            'address_line_1' => '12 Green Road',
            'district' => 'Dhaka',
            'country' => 'Bangladesh',
            'is_default_billing' => true,
            'is_default_shipping' => true,
            'is_active' => true,
        ]);

        $this->actingAs($sales)->post('/quotations', $this->payload($supplier))->assertSessionHasErrors('party_id');
        $this->actingAs($sales)->post('/quotations', $this->payload($inactive))->assertSessionHasErrors('party_id');
        $this->actingAs($sales)->post('/quotations', $this->payload($customer, [
            'lines' => [[
                'description' => 'Network survey',
                'unit' => 'job',
                'quantity' => '1.0005',
                'unit_price' => '10.00',
            ]],
        ]))->assertRedirect();

        $quotation = Quotation::query()->firstOrFail();
        $revision = $quotation->currentRevision()->firstOrFail();

        $this->assertSame('customer', $quotation->party_role);
        $this->assertSame($customer->id, $quotation->party_id);
        $this->assertSame(QuotationStatus::DRAFT, $quotation->status);
        $this->assertSame('Padma Engineering Ltd.', $revision->party_legal_name);
        $this->assertSame('Nadia Rahman', $revision->contact_name);
        $this->assertSame('012345678901', $revision->party_tin);
        $this->assertSame('10.01', $revision->total);
        $this->assertSame('excluded', $revision->tax_treatment);
        $this->assertStringContainsString('Tax is excluded', (string) $revision->tax_notice);
        $this->assertSame(1, $revision->lines()->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'quotation_created']);

        $customer->update(['legal_name' => 'Padma Engineering Limited']);
        $this->assertSame('Padma Engineering Ltd.', $revision->fresh()?->party_legal_name);

        $this->actingAs($sales)->put(route('quotations.update', $quotation), $this->payload($customer))->assertRedirect();
        $this->assertSame('Padma Engineering Limited', $revision->fresh()?->party_legal_name);
        $this->assertSame($revision->id, $quotation->fresh()?->current_revision_id);
    }

    public function test_sales_cannot_see_a_quotation_without_customer_access_and_filters_stay_bounded(): void
    {
        $sales = $this->userWithRole('sales-executive');
        $accounts = $this->userWithRole('accountant');
        $customer = $this->party();

        foreach (range(1, 16) as $number) {
            $this->actingAs($sales)->post('/quotations', $this->payload($customer, [
                'customer_notes' => sprintf('Quote %02d', $number),
                'lines' => [[
                    'description' => sprintf('Line %02d', $number),
                    'unit' => 'job',
                    'quantity' => '1',
                    'unit_price' => sprintf('%d.00', $number),
                ]],
            ]))->assertRedirect();
        }

        $first = Quotation::query()->orderBy('id')->firstOrFail();
        $last = Quotation::query()->orderByDesc('id')->firstOrFail();

        $this->actingAs($sales)->get('/quotations?page=2')
            ->assertOk()
            ->assertSee($first->number)
            ->assertDontSee($last->number);

        $this->actingAs($sales)->get('/quotations?number='.$last->number)
            ->assertOk()
            ->assertSee($last->number)
            ->assertDontSee($first->number);

        $this->actingAs($sales)->get('/quotations?customer='.urlencode('Padma'))
            ->assertOk()
            ->assertSee($last->number);

        $this->actingAs($sales)->get('/quotations?customer='.urlencode('%'))
            ->assertOk()
            ->assertDontSee($last->number);

        $role = Role::query()->where('slug', 'accountant')->firstOrFail();
        $role->permissions()->detach(Permission::query()->whereIn('slug', [
            'parties.view',
            'customers.view',
            'customers.manage',
        ])->pluck('id'));
        $accounts->unsetRelation('roles');

        $this->actingAs($accounts)->get('/quotations')->assertOk()->assertDontSee($last->number);
        $this->actingAs($accounts)->get(route('quotations.show', $last))->assertNotFound();
        $this->actingAs($sales)->get(route('quotations.show', $last))->assertOk()->assertSee('SECRET-NOTE');
    }

    public function test_installer_adds_quotation_grants_without_changing_other_custom_grants(): void
    {
        $this->seedAccess();
        $viewer = Role::query()->where('slug', 'viewer')->firstOrFail();
        $audit = Permission::query()->where('slug', 'audit.view')->firstOrFail();
        $viewer->permissions()->sync([$audit->id]);

        $sales = Role::query()->where('slug', 'sales-executive')->firstOrFail();
        $customers = Permission::query()->where('slug', 'customers.view')->firstOrFail();
        $sales->permissions()->detach($customers->id);
        $sales->permissions()->detach(Permission::query()->where('slug', 'quotations.view')->firstOrFail());

        app(QuotationModuleInstaller::class)->install();

        $viewer->unsetRelation('permissions');
        $sales->unsetRelation('permissions');

        $this->assertSame(['audit.view'], $viewer->permissions()->orderBy('slug')->pluck('slug')->all());
        $this->assertFalse($sales->permissions->contains('slug', 'customers.view'));
        $this->assertTrue($sales->permissions->contains('slug', 'quotations.view'));
        $this->assertFalse($sales->permissions->contains('slug', 'quotations.approve'));
        $this->assertFalse($sales->permissions->contains('slug', 'suppliers.view'));
    }

    public function test_quotations_upgrade_sql_matches_the_committed_file(): void
    {
        $committed = file_get_contents(base_path('deploy/upgrade-quotations-module.sql'));

        $this->assertIsString($committed);
        $this->assertSame($committed, QuotationModuleSql::upgrade());
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `quotations`', $committed);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `quotation_revisions`', $committed);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `quotation_lines`', $committed);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `quotation_share_tokens`', $committed);
        $this->assertStringContainsString('quotations.view', $committed);
        $this->assertStringContainsString('WHERE NOT EXISTS', $committed);
        $this->assertStringContainsString('NO_BACKSLASH_ESCAPES', $committed);
        $this->assertStringNotContainsString('DELETE FROM', $committed);
        $this->assertStringNotContainsString('DROP TABLE', $committed);
        $this->assertStringNotContainsString('INSERT INTO `users`', $committed);
    }

    public function test_a_quotation_row_cannot_be_hard_deleted(): void
    {
        $sales = $this->userWithRole('sales-executive');
        $customer = $this->party();
        $this->actingAs($sales)->post('/quotations', $this->payload($customer))->assertRedirect();
        $quotation = Quotation::query()->firstOrFail();

        $this->assertFalse($quotation->delete());
        $this->assertNotNull(Quotation::query()->find($quotation->id));
        $this->assertFalse($quotation->currentRevision?->delete());
        $this->assertInstanceOf(QuotationRevision::class, QuotationRevision::query()->find($quotation->current_revision_id));
    }

    private function party(string $role = 'customer', array $overrides = []): Party
    {
        $party = Party::query()->create(array_merge([
            'legal_name' => 'Padma Engineering Ltd.',
            'party_kind' => 'company',
            'status' => 'active',
            'currency_code' => 'BDT',
            'tin' => '012345678901',
            'email' => 'accounts@padma.example',
            'phone' => '01711000001',
        ], $overrides));
        $party->roles()->create(['role' => $role]);

        return $party;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Party $party, array $overrides = []): array
    {
        return array_merge([
            'party_id' => $party->id,
            'quotation_date' => '2026-11-01',
            'valid_until' => '2026-12-01',
            'terms' => 'Payment within 15 days.',
            'customer_notes' => 'Site visit included.',
            'internal_notes' => 'SECRET-NOTE',
            'lines' => [[
                'description' => 'Network survey',
                'unit' => 'job',
                'quantity' => '2',
                'unit_price' => '1500.50',
            ]],
        ], $overrides);
    }
}

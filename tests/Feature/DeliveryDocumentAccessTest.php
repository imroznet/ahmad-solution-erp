<?php

namespace Tests\Feature;

use App\Documents\DocumentModuleInstaller;
use App\Documents\DocumentModuleSql;
use App\Models\DeliveryChallan;
use App\Models\Party;
use App\Models\Permission;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DeliveryDocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00', 'Asia/Dhaka'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_permissions_hide_documents_and_changing_the_id_does_not_reveal_them(): void
    {
        $sales = $this->userWithRole('sales-executive');
        $manager = $this->userWithRole('sales-manager');
        $accountant = $this->userWithRole('accountant');
        $documents = $this->userWithRole('document-manager');
        $customer = $this->party();
        $quotation = $this->acceptedQuotation($sales, $manager, $customer);
        $this->actingAs($sales)->post('/challans', $this->challanPayload($quotation))->assertRedirect();
        $challan = DeliveryChallan::query()->firstOrFail();

        $this->actingAsGuest()->get('/challans')->assertRedirect(route('login'));
        $this->actingAsGuest()->get('/invoices')->assertRedirect(route('login'));
        $this->actingAs($documents)->get('/challans')->assertForbidden();
        $this->actingAs($documents)->get('/invoices')->assertForbidden();
        $this->actingAs($documents)->get(route('challans.show', $challan))->assertForbidden();
        $this->actingAs($accountant)->post('/challans', $this->challanPayload($quotation))->assertForbidden();
        $this->actingAs($sales)->post(route('challans.void', $challan), ['void_reason' => 'No'])->assertForbidden();
        $this->actingAs($sales)->get('/challans/999999')->assertNotFound();

        $this->actingAs($sales)->get(route('quotations.show', $quotation))
            ->assertOk()
            ->assertSee('Delivery challan')
            ->assertSee('Commercial invoice');
        $this->actingAs($sales)->get('/challans')->assertOk()->assertSee('Draft');
        $this->actingAs($accountant)->get(route('challans.show', $challan))->assertOk()->assertSee('SECRET-NOTE');

        $role = Role::query()->where('slug', 'accountant')->firstOrFail();
        $role->permissions()->detach(Permission::query()->whereIn('slug', [
            'parties.view',
            'customers.view',
            'customers.manage',
        ])->pluck('id'));
        $accountant->unsetRelation('roles');

        $this->actingAs($accountant)->get('/challans')->assertOk()->assertDontSee('Padma');
        $this->actingAs($accountant)->get(route('challans.show', $challan))->assertNotFound();
        $this->actingAs($accountant)->get(route('challans.pdf', $challan))->assertNotFound();
        $this->actingAs($sales)->get(route('challans.show', $challan))->assertOk()->assertSee('SECRET-NOTE');
    }

    public function test_installer_adds_document_grants_without_resetting_custom_grants(): void
    {
        $this->seedAccess();
        $viewer = Role::query()->where('slug', 'viewer')->firstOrFail();
        $audit = Permission::query()->where('slug', 'audit.view')->firstOrFail();
        $viewer->permissions()->sync([$audit->id]);

        $sales = Role::query()->where('slug', 'sales-executive')->firstOrFail();
        $sales->permissions()->detach(Permission::query()->where('slug', 'customers.view')->firstOrFail());
        $sales->permissions()->detach(Permission::query()->where('slug', 'challans.view')->firstOrFail());

        app(DocumentModuleInstaller::class)->install();

        $viewer->unsetRelation('permissions');
        $sales->unsetRelation('permissions');

        $this->assertSame(['audit.view'], $viewer->permissions()->orderBy('slug')->pluck('slug')->all());
        $this->assertFalse($sales->permissions->contains('slug', 'customers.view'));
        $this->assertTrue($sales->permissions->contains('slug', 'challans.view'));
        $this->assertFalse($sales->permissions->contains('slug', 'challans.void'));
        $this->assertFalse($sales->permissions->contains('slug', 'invoices.void'));
        $this->assertFalse($sales->permissions->contains('slug', 'suppliers.view'));
    }

    public function test_delivery_document_upgrade_sql_matches_the_committed_file(): void
    {
        $committed = file_get_contents(base_path('deploy/upgrade-delivery-documents-module.sql'));
        $this->assertIsString($committed);
        $this->assertSame($committed, DocumentModuleSql::upgrade());
    }

    private function acceptedQuotation(User $sales, User $manager, Party $customer): Quotation
    {
        $this->actingAs($sales)->post('/quotations', [
            'party_id' => $customer->id,
            'quotation_date' => '2026-11-01',
            'valid_until' => '2026-12-01',
            'internal_notes' => 'SECRET-NOTE',
            'lines' => [[
                'description' => 'Network survey',
                'unit' => 'job',
                'quantity' => '2',
                'unit_price' => '10.00',
            ]],
        ])->assertRedirect();
        $quotation = Quotation::query()->firstOrFail();
        $this->actingAs($sales)->post(route('quotations.submit', $quotation))->assertRedirect();
        $this->actingAs($manager)->post(route('quotations.approve', $quotation))->assertRedirect();
        $this->actingAs($sales)->post(route('quotations.send', $quotation), [
            'recipient_label' => 'Nadia Rahman',
        ])->assertRedirect();
        $this->actingAs($sales)->post(route('quotations.respond', $quotation), [
            'decision' => 'accepted',
            'respondent_name' => 'Nadia Rahman',
            'confirmed' => '1',
        ])->assertRedirect();

        return $quotation->fresh() ?? $quotation;
    }

    /**
     * @return array<string, mixed>
     */
    private function challanPayload(Quotation $quotation): array
    {
        $quotation->loadMissing('currentRevision.lines');

        return [
            'quotation_id' => $quotation->id,
            'challan_date' => '2026-11-04',
            'internal_notes' => 'SECRET-NOTE',
            'lines' => [[
                'quotation_line_id' => $quotation->currentRevision?->lines->first()?->id,
                'quantity' => '1',
            ]],
        ];
    }

    private function party(): Party
    {
        $party = Party::query()->create([
            'legal_name' => 'Padma Engineering Ltd.',
            'party_kind' => 'company',
            'status' => 'active',
            'currency_code' => 'BDT',
        ]);
        $party->roles()->create(['role' => 'customer']);

        return $party;
    }
}

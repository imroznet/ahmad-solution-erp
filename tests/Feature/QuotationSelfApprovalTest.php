<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Party;
use App\Models\Permission;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use App\Quotations\QuotationRuleException;
use App\Quotations\QuotationSelfApprovalSql;
use App\Quotations\QuotationStatus;
use App\Quotations\QuotationWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QuotationSelfApprovalTest extends TestCase
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

    public function test_a_super_administrator_can_approve_their_own_quotation(): void
    {
        $super = $this->userWithRole('super-administrator', ['name' => 'Super Owner']);
        $customer = $this->party();
        $quotation = $this->submittedBy($super, $customer);

        $this->actingAs($super)->get(route('quotations.approvals'))
            ->assertOk()
            ->assertSee('>Approve</a>', false);

        $this->actingAs($super)->post(route('quotations.approve', $quotation))->assertRedirect();

        $quotation->refresh();
        $this->assertSame(QuotationStatus::APPROVED, $quotation->status);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'quotation_approved',
            'user_id' => $super->id,
            'auditable_id' => $quotation->id,
        ]);

        $this->actingAs($super)->get(route('quotations.show', $quotation))
            ->assertOk()
            ->assertSee('Super Owner')
            ->assertSee('Recorded as an approval by the person who created or submitted this revision.')
            ->assertSee('Approved');

        $this->actingAs($super)->get(route('audit.index'))
            ->assertOk()
            ->assertSee('quotation approved')
            ->assertSee('Super Owner');
    }

    public function test_approve_without_the_own_permission_still_blocks_the_creator_and_allows_someone_else(): void
    {
        $sales = $this->userWithRole('sales-executive');
        $manager = $this->userWithRole('sales-manager', ['name' => 'Sales Manager']);
        $customer = $this->party();
        $own = $this->submittedBy($manager, $customer);

        $this->actingAs($manager)->get(route('quotations.approvals'))
            ->assertOk()
            ->assertSee($own->number)
            ->assertDontSee('>Approve</a>', false);
        $this->actingAs($manager)->get(route('quotations.show', $own))
            ->assertOk()
            ->assertDontSee('>Approve</button>', false);
        $this->actingAs($manager)->post(route('quotations.approve', $own))->assertForbidden();

        try {
            app(QuotationWorkflow::class)->approve($own->fresh() ?? $own, $manager);
            $this->fail('The creator must not approve their own revision.');
        } catch (QuotationRuleException $exception) {
            $this->assertSame('The person who created this revision cannot approve it.', $exception->getMessage());
        }

        $this->assertSame(QuotationStatus::SUBMITTED, $own->fresh()?->status);

        $other = $this->submittedBy($sales, $customer);
        $this->actingAs($manager)->get(route('quotations.approvals'))
            ->assertOk()
            ->assertSee('>Approve</a>', false);
        $this->actingAs($manager)->post(route('quotations.approve', $other))->assertRedirect();
        $this->assertSame(QuotationStatus::APPROVED, $other->fresh()?->status);
        $this->actingAs($manager)->get(route('quotations.show', $other))
            ->assertOk()
            ->assertDontSee('Recorded as an approval by the person who created or submitted this revision.');

        $this->actingAs($manager)->post(route('quotations.return', $own), [
            'remarks' => 'Please correct the quantity.',
        ])->assertRedirect();
        $this->assertSame(QuotationStatus::DRAFT, $own->fresh()?->status);
    }

    public function test_the_roles_page_grants_and_removes_self_approval(): void
    {
        $super = $this->userWithRole('super-administrator');
        $manager = $this->userWithRole('sales-manager', ['name' => 'Queue Manager']);
        $role = Role::query()->where('slug', 'sales-manager')->firstOrFail();
        $permission = Permission::query()->where('slug', 'quotations.approve_own')->firstOrFail();
        $customer = $this->party();

        $this->actingAs($super)->get(route('roles.edit', $role))
            ->assertOk()
            ->assertSee('Approve own quotations')
            ->assertSee('Approve a submitted quotation the user created or submitted themself.')
            ->assertDontSee('value="'.$permission->id.'" checked', false);

        $own = $this->submittedBy($manager, $customer);
        $this->actingAs($manager)->post(route('quotations.approve', $own))->assertForbidden();

        $granted = $role->permissions()->pluck('id')->all();
        $granted[] = $permission->id;
        $this->actingAs($super)->put(route('roles.update', $role), [
            'permissions' => $granted,
        ])->assertRedirect(route('roles.index'));

        $audit = AuditLog::query()->where('event', 'role_permissions_updated')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertStringContainsString('quotations.approve_own', (string) json_encode($audit->new_values));
        $this->assertStringNotContainsString('quotations.approve_own', (string) json_encode($audit->old_values));

        $this->seedAccess();
        $role->unsetRelation('permissions');
        $this->assertTrue($role->permissions()->get()->contains('slug', 'quotations.approve_own'));

        $manager->unsetRelation('roles');
        $this->actingAs($manager)->get(route('quotations.approvals'))
            ->assertOk()
            ->assertSee('>Approve</a>', false);
        $this->actingAs($manager)->get(route('quotations.show', $own))
            ->assertOk()
            ->assertSee('You created or submitted this revision. Approving it is recorded in your name.')
            ->assertSee('>Approve</button>', false);
        $this->actingAs($manager)->post(route('quotations.approve', $own))->assertRedirect();
        $this->assertSame(QuotationStatus::APPROVED, $own->fresh()?->status);
        $this->actingAs($manager)->get(route('quotations.show', $own))
            ->assertOk()
            ->assertSee('Queue Manager')
            ->assertSee('Recorded as an approval by the person who created or submitted this revision.');

        $second = $this->submittedBy($manager, $customer);
        $remaining = array_values(array_filter(
            $granted,
            fn (int|string $id): bool => (int) $id !== (int) $permission->id,
        ));
        $this->actingAs($super)->put(route('roles.update', $role), [
            'permissions' => $remaining,
        ])->assertRedirect(route('roles.index'));

        $manager->unsetRelation('roles');
        $this->actingAs($manager)->get(route('quotations.approvals'))
            ->assertOk()
            ->assertSee($second->number)
            ->assertDontSee('>Approve</a>', false);
        $this->actingAs($manager)->post(route('quotations.approve', $second))->assertForbidden();

        try {
            app(QuotationWorkflow::class)->approve($second->fresh() ?? $second, $manager);
            $this->fail('Removing the permission must block self-approval again.');
        } catch (QuotationRuleException $exception) {
            $this->assertSame('The person who created this revision cannot approve it.', $exception->getMessage());
        }

        $this->assertSame(QuotationStatus::SUBMITTED, $second->fresh()?->status);
    }

    public function test_a_submitter_who_did_not_create_the_revision_needs_the_permission(): void
    {
        $sales = $this->userWithRole('sales-executive', ['name' => 'Sales Executive']);
        $manager = $this->userWithRole('sales-manager', ['name' => 'Submitting Manager']);
        $role = Role::query()->where('slug', 'sales-manager')->firstOrFail();
        $permission = Permission::query()->where('slug', 'quotations.approve_own')->firstOrFail();
        $customer = $this->party();
        $quotation = $this->createQuotation($sales, $customer);

        $this->actingAs($manager)->post(route('quotations.submit', $quotation))->assertRedirect();
        $this->actingAs($manager)->post(route('quotations.approve', $quotation))->assertForbidden();

        try {
            app(QuotationWorkflow::class)->approve($quotation->fresh() ?? $quotation, $manager);
            $this->fail('The submitter must not approve the revision.');
        } catch (QuotationRuleException $exception) {
            $this->assertSame('The person who submitted this revision cannot approve it.', $exception->getMessage());
        }

        $granted = $role->permissions()->pluck('id')->all();
        $granted[] = $permission->id;
        $this->actingAs($this->userWithRole('super-administrator'))->put(route('roles.update', $role), [
            'permissions' => $granted,
        ])->assertRedirect();

        $manager->unsetRelation('roles');
        $this->actingAs($manager)->post(route('quotations.approve', $quotation))->assertRedirect();
        $this->assertSame(QuotationStatus::APPROVED, $quotation->fresh()?->status);
        $this->actingAs($manager)->get(route('quotations.show', $quotation))
            ->assertOk()
            ->assertSee('Submitting Manager')
            ->assertSee('Recorded as an approval by the person who created or submitted this revision.');
    }

    public function test_the_own_permission_does_not_replace_quotations_approve(): void
    {
        $sales = $this->userWithRole('sales-executive');
        $customer = $this->party();
        $quotation = $this->submittedBy($sales, $customer);
        $role = Role::query()->where('slug', 'sales-executive')->firstOrFail();
        $permission = Permission::query()->where('slug', 'quotations.approve_own')->firstOrFail();
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $sales->unsetRelation('roles');

        $this->actingAs($sales)->post(route('quotations.approve', $quotation))->assertForbidden();
        $this->assertSame(QuotationStatus::SUBMITTED, $quotation->fresh()?->status);
        $this->actingAs($sales)->post(route('quotations.return', $quotation), [
            'remarks' => 'Please correct the quantity.',
        ])->assertForbidden();
    }

    public function test_self_approval_sql_grants_only_the_super_administrator(): void
    {
        $committed = file_get_contents(base_path('deploy/upgrade-quotation-self-approval.sql'));

        $this->assertIsString($committed);
        $this->assertSame($committed, QuotationSelfApprovalSql::upgrade());
        $this->assertStringContainsString('quotations.approve_own', $committed);
        $this->assertStringContainsString('WHERE NOT EXISTS', $committed);
        $this->assertStringNotContainsString('DELETE FROM', $committed);
        $this->assertStringNotContainsString('DROP TABLE', $committed);
        $this->assertSame(1, substr_count($committed, 'INSERT INTO `permission_role`'));
    }

    private function submittedBy(User $actor, Party $customer): Quotation
    {
        $quotation = $this->createQuotation($actor, $customer);
        $this->actingAs($actor)->post(route('quotations.submit', $quotation))->assertRedirect();

        return $quotation->fresh() ?? $quotation;
    }

    private function createQuotation(User $actor, Party $customer): Quotation
    {
        $this->actingAs($actor)->post('/quotations', [
            'party_id' => $customer->id,
            'quotation_date' => '2026-11-01',
            'valid_until' => '2026-12-01',
            'terms' => 'Payment within 15 days.',
            'lines' => [[
                'description' => 'Network survey',
                'unit' => 'job',
                'quantity' => '2',
                'unit_price' => '1500.50',
            ]],
        ])->assertRedirect();

        return Quotation::query()->orderByDesc('id')->firstOrFail();
    }

    private function party(): Party
    {
        $party = Party::query()->create([
            'legal_name' => 'Padma Engineering Ltd.',
            'party_kind' => 'company',
            'status' => 'active',
            'currency_code' => 'BDT',
            'tin' => '012345678901',
            'email' => 'accounts@padma.example',
            'phone' => '01711000001',
        ]);
        $party->roles()->create(['role' => 'customer']);

        return $party;
    }
}

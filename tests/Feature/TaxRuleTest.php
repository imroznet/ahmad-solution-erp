<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\TaxRule;
use App\Tax\TaxModuleInstaller;
use App\Tax\TaxModuleSql;
use App\Tax\TaxRuleSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_statutory_rate_is_seeded_and_overlap_rules_are_enforced(): void
    {
        $this->seedAccess();
        $this->assertSame(0, TaxRule::query()->count());

        $manager = $this->userWithRole('accounts-manager');
        $accountant = $this->userWithRole('accountant');

        $this->actingAs($accountant)->post('/tax-rules', $this->rule())->assertForbidden();
        $this->actingAs($manager)->post('/tax-rules', $this->rule(['rate' => '15', 'rate_kind' => 'percentage']))
            ->assertRedirect();
        $draft = TaxRule::query()->firstOrFail();
        $this->assertSame('15.0000', $draft->nullableDecimal('rate'));
        $this->assertSame('draft', $draft->stringAttribute('status'));

        $this->actingAs($accountant)->post(route('tax-rules.approve', $draft))->assertForbidden();
        $this->actingAs($manager)->post(route('tax-rules.approve', $draft))->assertRedirect();
        $draft->refresh();
        $this->assertSame('approved', $draft->stringAttribute('status'));

        $this->actingAs($manager)->post('/tax-rules', $this->rule([
            'effective_from' => '2026-06-01',
            'effective_to' => '2026-06-30',
        ]))->assertRedirect();
        $overlap = TaxRule::query()->where('status', 'draft')->firstOrFail();
        $this->actingAs($manager)->post(route('tax-rules.approve', $overlap))
            ->assertSessionHasErrors('effective_from');
        $this->assertSame('draft', $overlap->fresh()->stringAttribute('status'));

        $this->actingAs($manager)->post(route('tax-rules.amend', $draft), [
            'effective_from' => '2026-07-01',
        ])->assertRedirect();
        $amendment = TaxRule::query()->where('status', 'draft')->latest('id')->firstOrFail();
        $this->assertSame(3, (int) $amendment->version);
        $this->assertSame('15.0000', $draft->fresh()->nullableDecimal('rate'));
        $this->actingAs($manager)->put(route('tax-rules.update', $amendment), $this->rule([
            'effective_from' => '2026-07-01',
            'effective_to' => '2026-07-31',
            'rate' => '7.5',
            'rate_kind' => 'percentage',
        ]))->assertRedirect();
        $this->actingAs($manager)->post(route('tax-rules.approve', $amendment))->assertRedirect();

        $draft->refresh();
        $amendment->refresh();
        $this->assertSame('2026-06-30', $draft->effective_to?->format('Y-m-d'));
        $this->assertSame('15.0000', $draft->nullableDecimal('rate'));
        $this->assertSame('7.5000', $amendment->nullableDecimal('rate'));

        $selector = app(TaxRuleSelector::class);
        $onStart = $selector->explain('output_vat', '2026-01-01', 'LOCAL-NOTE');
        $onEnd = $selector->explain('output_vat', '2026-06-30', 'LOCAL-NOTE');
        $next = $selector->explain('output_vat', '2026-07-01', 'LOCAL-NOTE');
        $after = $selector->explain('output_vat', '2026-08-01', 'LOCAL-NOTE');
        $missing = $selector->explain('vds', '2026-07-01', 'LOCAL-NOTE');

        $this->assertSame('matched', $onStart['outcome']);
        $this->assertSame(1, (int) $onStart['rules']->first()->version);
        $this->assertSame('matched', $onEnd['outcome']);
        $this->assertSame('matched', $next['outcome']);
        $this->assertSame(3, (int) $next['rules']->first()->version);
        $this->assertSame('none', $after['outcome']);
        $this->assertSame('none', $missing['outcome']);
        $this->assertStringContainsString('No rate is applied', $missing['reason']);
        $this->assertArrayNotHasKey('rate', $missing);

        $this->actingAs($manager)->get('/tax-rules/preview?tax_type=output_vat&on=2026-07-01&rule_key=LOCAL-NOTE')
            ->assertOk()
            ->assertSee('not copied onto a document');
    }

    public function test_a_retired_rule_is_not_a_match_and_custom_grants_survive_install(): void
    {
        $manager = $this->userWithRole('accounts-manager');
        $this->actingAs($manager)->post('/tax-rules', $this->rule())->assertRedirect();
        $rule = TaxRule::query()->firstOrFail();
        $this->actingAs($manager)->post(route('tax-rules.approve', $rule))->assertRedirect();
        $this->actingAs($manager)->post(route('tax-rules.retire', $rule), [
            'retire_reason' => 'Superseded by a later instrument.',
        ])->assertRedirect();

        $result = app(TaxRuleSelector::class)->explain('output_vat', '2026-03-01', 'LOCAL-NOTE');
        $this->assertSame('none', $result['outcome']);

        $viewer = Role::query()->where('slug', 'viewer')->firstOrFail();
        $audit = Permission::query()->where('slug', 'audit.view')->firstOrFail();
        $viewer->permissions()->sync([$audit->id]);
        $sales = Role::query()->where('slug', 'sales-executive')->firstOrFail();
        $sales->permissions()->detach(Permission::query()->where('slug', 'mushak.view')->firstOrFail());
        $viewer->permissions()->attach(Permission::query()->where('slug', 'mushak.view')->firstOrFail());

        app(TaxModuleInstaller::class)->install();
        $viewer->unsetRelation('permissions');
        $sales->unsetRelation('permissions');

        $this->assertTrue($viewer->permissions->contains('slug', 'audit.view'));
        $this->assertTrue($viewer->permissions->contains('slug', 'mushak.view'));
        $this->assertTrue($sales->permissions->contains('slug', 'mushak.view'));
        $this->assertFalse($sales->permissions->contains('slug', 'mushak.void'));
        $this->assertFalse($sales->permissions->contains('slug', 'tax_rules.approve'));
    }

    public function test_tax_upgrade_sql_matches_the_committed_file(): void
    {
        $committed = file_get_contents(base_path('deploy/upgrade-tax-module.sql'));
        $this->assertIsString($committed);
        $this->assertSame($committed, TaxModuleSql::upgrade());
        $this->assertStringNotContainsString('15.00', $committed);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `mushak_documents`', $committed);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `vds_records`', $committed);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `ait_records`', $committed);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function rule(array $overrides = []): array
    {
        return array_merge([
            'tax_type' => 'output_vat',
            'rule_key' => 'LOCAL-NOTE',
            'name' => 'Operator note, not a statutory rate',
            'legal_instrument' => 'Unverified internal note',
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
            'notes' => 'Do not apply this rate automatically.',
        ], $overrides);
    }
}

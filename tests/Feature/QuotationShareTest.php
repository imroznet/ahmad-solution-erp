<?php

namespace Tests\Feature;

use App\Models\Party;
use App\Models\Quotation;
use App\Models\QuotationRevision;
use App\Models\User;
use App\Quotations\QuotationPdfRenderer;
use App\Quotations\QuotationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuotationShareTest extends TestCase
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

    public function test_share_links_store_a_hash_and_the_public_page_hides_internal_notes(): void
    {
        $sales = $this->userWithRole('sales-executive');
        $manager = $this->userWithRole('sales-manager');
        $quotation = $this->advanceToSent($sales, $manager, $this->party());
        $token = $this->share($sales, $quotation);

        $stored = DB::table('quotation_share_tokens')->sole();
        $this->assertSame(hash('sha256', $token), $stored->token_hash);
        $this->assertStringNotContainsString($token, json_encode($stored, JSON_THROW_ON_ERROR));

        $created = DB::table('audit_logs')->where('event', 'quotation_share_created')->sole();
        $this->assertStringNotContainsString($token, json_encode($created, JSON_THROW_ON_ERROR));

        $page = $this->get('/q/'.$token);
        $page->assertOk();
        $page->assertSee('Padma Engineering Ltd.');
        $page->assertSee('Network survey');
        $page->assertSee('Tax is excluded');
        $page->assertDontSee('SECRET-NOTE');
        $page->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertMatchesRegularExpression('/private/', (string) $page->headers->get('Cache-Control'));
        $this->assertMatchesRegularExpression('/no-store/', (string) $page->headers->get('Cache-Control'));

        $previous = session()->previousUrl();
        $this->assertIsString($previous);
        $this->assertStringContainsString('/q/[redacted]', $previous);
        $this->assertStringNotContainsString($token, $previous);

        $viewed = DB::table('audit_logs')->where('event', 'quotation_share_viewed')->sole();
        $this->assertStringNotContainsString($token, (string) $viewed->url);
        $this->assertStringNotContainsString($token, json_encode($viewed, JSON_THROW_ON_ERROR));

        $access = DB::table('quotation_share_access_logs')->where('event', 'viewed')->sole();
        $this->assertStringNotContainsString($token, json_encode($access, JSON_THROW_ON_ERROR));
        $this->assertNotNull($access->quotation_share_token_id);
    }

    public function test_missing_expired_and_revoked_links_return_the_same_page(): void
    {
        $sales = $this->userWithRole('sales-executive');
        $manager = $this->userWithRole('sales-manager');
        $quotation = $this->advanceToSent($sales, $manager, $this->party());
        $token = $this->share($sales, $quotation);
        $missing = str_repeat('B', 43);

        $unknown = $this->get('/q/'.$missing);
        $unknown->assertNotFound();
        $unknown->assertSee('This quotation link is not available');
        $unknown->assertDontSee($missing);

        DB::table('quotation_share_tokens')->update(['expires_at' => now()->subMinute()]);
        $expired = $this->get('/q/'.$token);
        $expired->assertNotFound();
        $this->assertSame($unknown->getContent(), $expired->getContent());
        $this->assertDatabaseHas('quotation_share_access_logs', ['event' => 'expired']);

        DB::table('quotation_share_tokens')->update([
            'expires_at' => now()->addDay(),
            'revoked_at' => now(),
        ]);
        $revoked = $this->get('/q/'.$token);
        $revoked->assertNotFound();
        $this->assertSame($unknown->getContent(), $revoked->getContent());
        $this->assertStringNotContainsString($token, (string) $revoked->getContent());

        $shareId = DB::table('quotation_share_tokens')->value('id');
        $this->actingAs($sales)->post(route('quotations.shares.revoke', [
            'quotation' => $quotation,
            'share' => $shareId,
        ]))->assertRedirect();
        $this->assertSame(1, DB::table('audit_logs')->where('event', 'quotation_share_revoked')->count());
    }

    public function test_public_acceptance_is_idempotent_and_a_conflict_is_rejected(): void
    {
        $sales = $this->userWithRole('sales-executive');
        $manager = $this->userWithRole('sales-manager');
        $quotation = $this->advanceToSent($sales, $manager, $this->party());
        $token = $this->share($sales, $quotation);
        $payload = [
            'decision' => 'accepted',
            'respondent_name' => 'Nadia Rahman',
            'respondent_designation' => 'Manager',
            'remarks' => 'Please proceed.',
            'confirmed' => '1',
        ];

        $this->post('/q/'.$token.'/respond', $payload)->assertRedirect('/q/'.$token);
        $this->post('/q/'.$token.'/respond', $payload)->assertRedirect('/q/'.$token);

        $this->assertSame(1, DB::table('quotation_responses')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('event', 'quotation_accepted')->count());
        $this->assertSame(QuotationStatus::ACCEPTED, $quotation->fresh()?->status);
        $this->assertDatabaseHas('quotation_responses', [
            'decision' => 'accepted',
            'source' => 'public_share',
            'respondent_name' => 'Nadia Rahman',
        ]);

        $this->post('/q/'.$token.'/respond', [
            ...$payload,
            'decision' => 'rejected',
        ])->assertSessionHasErrors('decision');
        $this->assertSame(1, DB::table('quotation_responses')->count());
        $this->assertSame(QuotationStatus::ACCEPTED, $quotation->fresh()?->status);
    }

    public function test_a_superseded_link_stays_readable_and_cannot_be_accepted(): void
    {
        $sales = $this->userWithRole('sales-executive');
        $manager = $this->userWithRole('sales-manager');
        $customer = $this->party();
        $quotation = $this->advanceToSent($sales, $manager, $customer);
        $token = $this->share($sales, $quotation);

        $this->actingAs($sales)->post(route('quotations.revisions.store', $quotation), [
            'party_id' => $customer->id,
            'quotation_date' => '2026-11-01',
            'valid_until' => '2026-12-01',
            'terms' => 'Payment within 15 days.',
            'internal_notes' => 'SECRET-NOTE',
            'lines' => [[
                'description' => 'Revised cable route',
                'unit' => 'meter',
                'quantity' => '3',
                'unit_price' => '100.00',
            ]],
        ])->assertRedirect();

        $page = $this->get('/q/'.$token);
        $page->assertOk();
        $page->assertSee('Network survey');
        $page->assertSee('superseded');
        $page->assertDontSee('SECRET-NOTE');
        $page->assertDontSee('Revised cable route');

        $this->post('/q/'.$token.'/respond', [
            'decision' => 'accepted',
            'respondent_name' => 'Nadia Rahman',
            'confirmed' => '1',
        ])->assertSessionHasErrors('decision');
        $this->assertDatabaseCount('quotation_responses', 0);
        $this->assertSame(QuotationStatus::SENT, QuotationRevision::query()->where('revision_number', 1)->firstOrFail()->status);
    }

    public function test_a_public_response_after_the_validity_date_expires_without_a_response_row(): void
    {
        $sales = $this->userWithRole('sales-executive');
        $manager = $this->userWithRole('sales-manager');
        $quotation = $this->advanceToSent($sales, $manager, $this->party(), [
            'quotation_date' => '2026-11-01',
            'valid_until' => '2026-11-10',
        ]);
        $token = $this->share($sales, $quotation, 14);
        $payload = [
            'decision' => 'rejected',
            'respondent_name' => 'Nadia Rahman',
            'confirmed' => '1',
        ];

        $this->actingAsGuest();
        Carbon::setTestNow(Carbon::parse('2026-11-11 12:00:00', 'Asia/Dhaka'));
        $expiresAt = DB::table('quotation_share_tokens')->value('expires_at');
        $this->assertTrue(Carbon::parse((string) $expiresAt)->greaterThan(now()));

        $posted = $this->post('/q/'.$token.'/respond', $payload);
        $posted->assertSessionHasErrors('decision');
        $cookie = $posted->getCookie((string) config('session.cookie'));
        $this->assertNotNull($cookie);
        $this->withCookie($cookie->getName(), $cookie->getValue())
            ->get('/q/'.$token)
            ->assertOk()
            ->assertSee('past its validity date')
            ->assertDontSee('I confirm this response');

        $quotation->refresh();
        $this->assertSame(QuotationStatus::EXPIRED, $quotation->status);
        $this->assertNull($quotation->currentRevision?->expired_by);
        $this->assertDatabaseCount('quotation_responses', 0);
        $this->assertSame(1, DB::table('audit_logs')->where('event', 'quotation_expired')->count());
        $this->assertNull(DB::table('audit_logs')->where('event', 'quotation_expired')->value('user_id'));

        $this->post('/q/'.$token.'/respond', $payload)->assertSessionHasErrors('decision');
        $this->assertDatabaseCount('quotation_responses', 0);
        $this->assertSame(1, DB::table('audit_logs')->where('event', 'quotation_expired')->count());
        $this->get('/q/'.$token)->assertOk();
    }

    public function test_public_reads_are_rate_limited_without_logging_the_token(): void
    {
        config([
            'asl.quotations.read_per_minute' => 2,
        ]);

        $sales = $this->userWithRole('sales-executive');
        $manager = $this->userWithRole('sales-manager');
        $token = $this->share($sales, $this->advanceToSent($sales, $manager, $this->party()));

        $this->get('/q/'.$token)->assertOk();
        $this->get('/q/'.$token)->assertOk();
        $limited = $this->get('/q/'.$token);
        $limited->assertStatus(429);
        $limited->assertDontSee($token);
        $this->assertDatabaseHas('quotation_share_access_logs', ['event' => 'rate_limited']);
        $this->assertStringNotContainsString($token, (string) json_encode(DB::table('quotation_share_access_logs')->get()->all()));
    }

    public function test_the_pdf_is_the_revision_being_viewed_and_stays_private(): void
    {
        $sales = $this->userWithRole('sales-executive');
        $manager = $this->userWithRole('sales-manager');
        $quotation = $this->advanceToSent($sales, $manager, $this->party());
        $revision = $quotation->currentRevision()->with('lines')->firstOrFail();
        $html = view('quotations.pdf', [
            'quotation' => $quotation,
            'revision' => $revision,
            'company' => app(QuotationPdfRenderer::class)->letterhead(),
            'logo' => null,
        ])->render();

        $this->assertStringContainsString('Padma Engineering Ltd.', $html);
        $this->assertStringContainsString('Network survey', $html);
        $this->assertStringContainsString('৳3,001.00', $html);
        $this->assertStringNotContainsString('SECRET-NOTE', $html);

        $pdf = $this->actingAs($sales)->get(route('quotations.pdf', [
            'quotation' => $quotation,
            'revision' => 1,
        ]));
        $pdf->assertOk();
        $pdf->assertHeader('Content-Type', 'application/pdf');
        $this->assertMatchesRegularExpression('/private/', (string) $pdf->headers->get('Cache-Control'));
        $this->assertMatchesRegularExpression('/no-store/', (string) $pdf->headers->get('Cache-Control'));
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());
        $this->assertDatabaseHas('audit_logs', ['event' => 'quotation_pdf_downloaded']);

        $token = $this->share($sales, $quotation);
        $public = $this->get('/q/'.$token.'/pdf');
        $public->assertOk();
        $public->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $public->getContent());
        auth()->logout();
        $this->get('/quotations/'.$quotation->id.'/revisions/1/pdf')->assertRedirect(route('login'));
    }

    private function share(User $sales, Quotation $quotation, int $days = 7): string
    {
        $this->actingAs($sales)->post(route('quotations.shares.store', $quotation), [
            'days' => $days,
        ])->assertRedirect()->assertSessionHas('quotation_share_url');

        $url = session('quotation_share_url');
        $this->assertIsString($url);
        $this->assertMatchesRegularExpression('#/q/[A-Za-z0-9_-]{43}$#', $url);

        return (string) preg_replace('#^.*/q/#', '', $url);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function advanceToSent(User $sales, User $manager, Party $customer, array $overrides = []): Quotation
    {
        $this->actingAs($sales)->post('/quotations', array_merge([
            'party_id' => $customer->id,
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
        ], $overrides))->assertRedirect();

        $quotation = Quotation::query()->orderByDesc('id')->firstOrFail();
        $this->actingAs($sales)->post(route('quotations.submit', $quotation))->assertRedirect();
        $this->actingAs($manager)->post(route('quotations.approve', $quotation))->assertRedirect();
        $this->actingAs($sales)->post(route('quotations.send', $quotation))->assertRedirect();

        return $quotation->fresh() ?? $quotation;
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

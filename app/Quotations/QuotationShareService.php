<?php

namespace App\Quotations;

use App\Models\Quotation;
use App\Models\QuotationResponse;
use App\Models\QuotationRevision;
use App\Models\QuotationShareAccessLog;
use App\Models\QuotationShareToken;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class QuotationShareService
{
    public function __construct(
        private readonly QuotationLock $lock,
        private readonly QuotationWorkflow $workflow,
        private readonly AuditLogger $audit,
    ) {}

    public function create(Quotation $quotation, User $actor, int $days): string
    {
        $maximum = max(1, (int) config('asl.quotations.share_max_days', 90));
        $days = min($maximum, max(1, $days));

        return DB::transaction(function () use ($quotation, $actor, $days): string {
            [$quotation, $revision] = $this->lock->current((int) $quotation->getKey());

            if ($revision->stringAttribute('status') !== QuotationStatus::SENT) {
                throw new QuotationRuleException('share', 'Create a share link after the quotation has been sent.');
            }

            $raw = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $token = QuotationShareToken::query()->create([
                'quotation_revision_id' => $revision->getKey(),
                'token_hash' => hash('sha256', $raw),
                'expires_at' => now()->addDays($days),
                'created_by' => $actor->getKey(),
                'created_at' => now(),
            ]);
            $expiresAt = $token->getAttribute('expires_at');
            $this->audit->record('quotation_share_created', $quotation, newValues: [
                'number' => $quotation->stringAttribute('number'),
                'revision_number' => $revision->revision_number,
                'share_id' => $token->getKey(),
                'expires_at' => $expiresAt instanceof \DateTimeInterface
                    ? Carbon::parse($expiresAt)->timezone('Asia/Dhaka')->toIso8601String()
                    : null,
            ], actor: $actor);

            return $raw;
        });
    }

    public function revoke(Quotation $quotation, QuotationShareToken $token, User $actor): void
    {
        DB::transaction(function () use ($quotation, $token, $actor): void {
            $locked = QuotationShareToken::query()->whereKey($token->getKey())->lockForUpdate()->first();

            if (! $locked instanceof QuotationShareToken) {
                throw new QuotationRuleException('share', 'This share link could not be found.');
            }

            $revision = QuotationRevision::query()->find($locked->quotation_revision_id);

            if (! $revision instanceof QuotationRevision || (int) $revision->quotation_id !== (int) $quotation->getKey()) {
                throw new QuotationRuleException('share', 'This share link does not belong to the quotation.');
            }

            if ($locked->revoked_at === null) {
                $locked->revoked_at = now();
                $locked->save();
            }

            $this->audit->record('quotation_share_revoked', $quotation, newValues: [
                'number' => $quotation->stringAttribute('number'),
                'revision_number' => $revision->revision_number,
                'share_id' => $locked->getKey(),
            ], actor: $actor);
        });
    }

    public function resolve(string $hash): ?QuotationShareToken
    {
        if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
            $this->log(null, 'invalid');

            return null;
        }

        $token = QuotationShareToken::query()->where('token_hash', $hash)->first();

        if (! $token instanceof QuotationShareToken) {
            $this->log(null, 'invalid');

            return null;
        }

        if ($token->getAttribute('revoked_at') !== null) {
            $this->log((int) $token->getKey(), 'revoked');

            return null;
        }

        if (! $token->expiryIsFuture()) {
            $this->log((int) $token->getKey(), 'expired');

            return null;
        }

        return $token;
    }

    public function recordView(QuotationShareToken $token, bool $pdf): QuotationRevision
    {
        $revision = $token->revision()->with(['lines', 'quotation'])->first();

        if (! $revision instanceof QuotationRevision || ! $revision->quotation instanceof Quotation) {
            $this->log((int) $token->getKey(), 'invalid');

            throw new QuotationRuleException('share', 'This quotation link is not available.');
        }

        $this->log((int) $token->getKey(), $pdf ? 'pdf' : 'viewed');
        $this->audit->record($pdf ? 'quotation_share_pdf' : 'quotation_share_viewed', $revision->quotation, newValues: [
            'number' => $revision->quotation->stringAttribute('number'),
            'revision_number' => $revision->revision_number,
            'share_id' => $token->getKey(),
        ]);

        return $revision;
    }

    public function recordRateLimit(string $hash): void
    {
        if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
            $this->log(null, 'rate_limited');

            return;
        }

        $id = QuotationShareToken::query()->where('token_hash', $hash)->value('id');
        $this->log(is_numeric($id) ? (int) $id : null, 'rate_limited');
    }

    public function respond(
        QuotationShareToken $token,
        string $decision,
        string $respondentName,
        ?string $designation,
        ?string $remarks,
    ): QuotationResponse {
        $request = request();

        return $this->workflow->respond(
            $this->quotationForToken($token),
            null,
            $decision,
            'public_share',
            $respondentName,
            $designation,
            $remarks,
            (int) $token->getKey(),
            $request->ip(),
            $this->agent($request),
            $token,
        );
    }

    /**
     * The workflow locks the token and the current revision in one transaction.
     * A missing quotation is replaced there; this lookup only supplies the argument.
     */
    private function quotationForToken(QuotationShareToken $token): Quotation
    {
        $revision = QuotationRevision::query()->find($token->quotation_revision_id);
        $quotation = $revision instanceof QuotationRevision
            ? Quotation::query()->find($revision->quotation_id)
            : null;

        return $quotation instanceof Quotation ? $quotation : new Quotation;
    }

    private function log(?int $tokenId, string $event): void
    {
        $request = request();

        QuotationShareAccessLog::query()->create([
            'quotation_share_token_id' => $tokenId,
            'event' => $event,
            'ip_address' => $request->ip(),
            'user_agent' => $this->agent($request),
            'created_at' => now(),
        ]);
    }

    private function agent(Request $request): ?string
    {
        $agent = $request->userAgent();

        if (! is_string($agent) || $agent === '') {
            return null;
        }

        return Str::limit($agent, 512, '');
    }
}

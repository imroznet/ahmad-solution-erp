<?php

namespace App\Quotations;

use App\Models\Quotation;
use App\Models\QuotationApproval;
use App\Models\QuotationResponse;
use App\Models\QuotationRevision;
use App\Models\QuotationSend;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class QuotationWorkflow
{
    public function __construct(
        private readonly QuotationLock $lock,
        private readonly AuditLogger $audit,
    ) {}

    public function submit(Quotation $quotation, User $actor, ?string $remarks = null): void
    {
        DB::transaction(function () use ($quotation, $actor, $remarks): void {
            [$quotation, $revision] = $this->lock->current((int) $quotation->getKey());
            $this->move($quotation, $revision, QuotationStatus::SUBMITTED);
            $this->approval($revision, 'submitted', $actor, $remarks);
            $this->audit->record('quotation_submitted', $quotation, newValues: $this->payload($quotation, $revision), actor: $actor);
        });
    }

    public function approve(Quotation $quotation, User $actor, ?string $remarks = null): void
    {
        DB::transaction(function () use ($quotation, $actor, $remarks): void {
            [$quotation, $revision] = $this->lock->current((int) $quotation->getKey());
            $this->assertCanApprove($revision, $actor);
            $this->move($quotation, $revision, QuotationStatus::APPROVED);
            $this->approval($revision, 'approved', $actor, $remarks);
            $this->audit->record('quotation_approved', $quotation, newValues: $this->payload($quotation, $revision), actor: $actor);
        });
    }

    public function returnToDraft(Quotation $quotation, User $actor, string $remarks): void
    {
        DB::transaction(function () use ($quotation, $actor, $remarks): void {
            [$quotation, $revision] = $this->lock->current((int) $quotation->getKey());
            $this->move($quotation, $revision, QuotationStatus::DRAFT);
            $this->approval($revision, 'returned', $actor, $remarks);
            $this->audit->record('quotation_returned', $quotation, newValues: [
                ...$this->payload($quotation, $revision),
                'remarks' => $remarks,
            ], actor: $actor);
        });
    }

    public function send(Quotation $quotation, User $actor, ?string $recipient, ?string $remarks): void
    {
        DB::transaction(function () use ($quotation, $actor, $recipient, $remarks): void {
            [$quotation, $revision] = $this->lock->current((int) $quotation->getKey());
            $this->move($quotation, $revision, QuotationStatus::SENT);
            QuotationSend::query()->create([
                'quotation_revision_id' => $revision->getKey(),
                'sent_by' => $actor->getKey(),
                'recipient_label' => $this->limited($recipient, 200),
                'delivery_status' => QuotationSend::EMAIL_NOT_CONFIGURED,
                'remarks' => $this->limited($remarks, 2000),
                'created_at' => now(),
            ]);
            $this->audit->record('quotation_sent', $quotation, newValues: [
                ...$this->payload($quotation, $revision),
                'delivery_status' => QuotationSend::EMAIL_NOT_CONFIGURED,
                'recipient_label' => $this->limited($recipient, 200),
            ], actor: $actor);
        });
    }

    public function cancel(Quotation $quotation, User $actor, string $reason): void
    {
        DB::transaction(function () use ($quotation, $actor, $reason): void {
            [$quotation, $revision] = $this->lock->current((int) $quotation->getKey());
            $from = $revision->stringAttribute('status');

            if (! QuotationStatus::canMove($from, QuotationStatus::CANCELLED)) {
                throw new QuotationRuleException('quotation', 'This quotation cannot be cancelled from '.QuotationStatus::label($from).'.');
            }

            $revision->forceFill([
                'status' => QuotationStatus::CANCELLED,
                'cancel_reason' => $reason,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->getKey(),
            ])->save();
            $quotation->status = QuotationStatus::CANCELLED;
            $quotation->save();
            $this->audit->record('quotation_cancelled', $quotation, newValues: [
                ...$this->payload($quotation, $revision),
                'cancel_reason' => $reason,
            ], actor: $actor);
        });
    }

    public function expire(Quotation $quotation, User $actor): void
    {
        DB::transaction(function () use ($quotation, $actor): void {
            [$quotation, $revision] = $this->lock->current((int) $quotation->getKey());

            if (! $revision->isPastValidity()) {
                throw new QuotationRuleException('valid_until', 'This quotation is still within its validity date.');
            }

            $this->move($quotation, $revision, QuotationStatus::EXPIRED);
            $revision->forceFill([
                'expired_at' => now(),
                'expired_by' => $actor->getKey(),
            ])->save();
            $this->audit->record('quotation_expired', $quotation, newValues: $this->payload($quotation, $revision), actor: $actor);
        });
    }

    public function respond(
        Quotation $quotation,
        ?User $actor,
        string $decision,
        string $source,
        string $respondentName,
        ?string $designation,
        ?string $remarks,
        ?int $shareTokenId,
        ?string $ipAddress,
        ?string $userAgent,
    ): QuotationResponse {
        return DB::transaction(function () use ($quotation, $actor, $decision, $source, $respondentName, $designation, $remarks, $shareTokenId, $ipAddress, $userAgent): QuotationResponse {
            [$quotation, $revision] = $this->lock->current((int) $quotation->getKey());

            if (! in_array($decision, ['accepted', 'rejected'], true)) {
                throw new QuotationRuleException('decision', 'Choose acceptance or rejection.');
            }

            $existing = QuotationResponse::query()->where('quotation_revision_id', $revision->getKey())->first();

            if ($existing instanceof QuotationResponse) {
                if ($existing->getAttribute('decision') === $decision) {
                    return $existing;
                }

                throw new QuotationRuleException('decision', 'This revision already has a different response.');
            }

            $target = $decision === 'accepted' ? QuotationStatus::ACCEPTED : QuotationStatus::REJECTED;
            $this->move($quotation, $revision, $target);

            try {
                $response = QuotationResponse::query()->create([
                    'quotation_revision_id' => $revision->getKey(),
                    'decision' => $decision,
                    'source' => $source,
                    'respondent_name' => $respondentName,
                    'respondent_designation' => $this->limited($designation, 120),
                    'remarks' => $this->limited($remarks, 2000),
                    'recorded_by' => $actor?->getKey(),
                    'quotation_share_token_id' => $shareTokenId,
                    'ip_address' => $this->limited($ipAddress, 45),
                    'user_agent' => $this->limited($userAgent, 512),
                    'created_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                $winner = QuotationResponse::query()->where('quotation_revision_id', $revision->getKey())->first();

                if ($winner instanceof QuotationResponse && $winner->getAttribute('decision') === $decision) {
                    return $winner;
                }

                throw new QuotationRuleException('decision', 'This revision already has a different response.');
            }

            $this->audit->record(
                $decision === 'accepted' ? 'quotation_accepted' : 'quotation_rejected',
                $quotation,
                newValues: [
                    ...$this->payload($quotation, $revision),
                    'source' => $source,
                    'respondent_name' => $respondentName,
                    'share_id' => $shareTokenId,
                ],
                actor: $actor,
            );

            return $response;
        });
    }

    private function assertCanApprove(QuotationRevision $revision, User $actor): void
    {
        if ((int) $revision->created_by === (int) $actor->getKey()) {
            throw new QuotationRuleException('quotation', 'The person who created this revision cannot approve it.');
        }

        $submitter = QuotationApproval::query()
            ->where('quotation_revision_id', $revision->getKey())
            ->where('action', 'submitted')
            ->latest('id')
            ->value('user_id');

        if ($submitter !== null && (int) $submitter === (int) $actor->getKey()) {
            throw new QuotationRuleException('quotation', 'The person who submitted this revision cannot approve it.');
        }
    }

    private function move(Quotation $quotation, QuotationRevision $revision, string $to): void
    {
        $from = $revision->stringAttribute('status');

        if (! QuotationStatus::canMove($from, $to)) {
            throw new QuotationRuleException('quotation', 'This quotation cannot move from '.QuotationStatus::label($from).' to '.QuotationStatus::label($to).'.');
        }

        $revision->status = $to;
        $revision->save();
        $quotation->status = $to;
        $quotation->save();
    }

    private function approval(QuotationRevision $revision, string $action, User $actor, ?string $remarks): void
    {
        QuotationApproval::query()->create([
            'quotation_revision_id' => $revision->getKey(),
            'action' => $action,
            'user_id' => $actor->getKey(),
            'remarks' => $this->limited($remarks, 2000),
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Quotation $quotation, QuotationRevision $revision): array
    {
        return [
            'number' => $quotation->stringAttribute('number'),
            'revision_number' => $revision->revision_number,
            'revision_id' => $revision->getKey(),
            'status' => $revision->stringAttribute('status'),
            'party_id' => $quotation->party_id,
            'party_role' => $quotation->stringAttribute('party_role'),
        ];
    }

    private function limited(?string $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return Str::limit($trimmed, $limit, '');
    }
}

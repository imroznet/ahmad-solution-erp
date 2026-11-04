<?php

namespace App\Quotations;

use App\Models\Party;
use App\Models\Quotation;
use App\Models\QuotationRevision;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class QuotationWriter
{
    public function __construct(
        private readonly QuotationLock $lock,
        private readonly QuotationNumberAllocator $numbers,
        private readonly QuotationCalculator $calculator,
        private readonly QuotationSnapshotter $snapshots,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(User $actor, array $input): Quotation
    {
        $attempt = 0;

        while (true) {
            try {
                return DB::transaction(function () use ($actor, $input): Quotation {
                    $party = $this->lock->activeCustomer($this->integer($input, 'party_id'));
                    $prepared = $this->prepare($party, $input);
                    $quotation = Quotation::query()->create([
                        'number' => $this->numbers->allocate(),
                        'party_id' => $party->getKey(),
                        'party_role' => 'customer',
                        'status' => QuotationStatus::DRAFT,
                        'created_by' => $actor->getKey(),
                    ]);
                    $revision = $this->storeRevision($quotation, 1, $actor, $prepared);
                    $quotation->current_revision_id = $revision->getKey();
                    $quotation->save();
                    $this->audit->record('quotation_created', $quotation, newValues: $this->auditPayload($quotation, $revision), actor: $actor);

                    return $quotation;
                }, 5);
            } catch (UniqueConstraintViolationException $exception) {
                $attempt++;

                if ($attempt >= 5 || ! str_contains($exception->getMessage(), 'quotations')) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateDraft(Quotation $quotation, User $actor, array $input): Quotation
    {
        return DB::transaction(function () use ($quotation, $actor, $input): Quotation {
            [$quotation, $revision] = $this->lock->current((int) $quotation->getKey());

            if ($revision->stringAttribute('status') !== QuotationStatus::DRAFT) {
                throw new QuotationRuleException('quotation', 'Only a draft can be edited. Open a new revision for an issued quotation.');
            }

            $party = $this->lock->activeCustomer($this->integer($input, 'party_id'));
            $prepared = $this->prepare($party, $input);
            $this->replaceLines($revision, $prepared['lines']);
            $revision->fill($prepared['attributes'])->save();
            $quotation->party_id = $party->getKey();
            $quotation->party_role = 'customer';
            $quotation->status = QuotationStatus::DRAFT;
            $quotation->save();
            $this->audit->record(
                'quotation_revised',
                $quotation,
                newValues: $this->auditPayload($quotation, $revision->refresh()),
                actor: $actor,
            );

            return $quotation;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function revise(Quotation $quotation, User $actor, array $input): Quotation
    {
        return DB::transaction(function () use ($quotation, $actor, $input): Quotation {
            [$quotation, $previous] = $this->lock->current((int) $quotation->getKey());

            if (! in_array($previous->stringAttribute('status'), QuotationStatus::REVISABLE, true)) {
                throw new QuotationRuleException('quotation', 'A new revision can be opened from an approved, sent, rejected, or expired quotation.');
            }

            $party = $this->lock->activeCustomer((int) $quotation->party_id);
            $input['party_id'] = $party->getKey();
            $prepared = $this->prepare($party, $input);
            $nextNumber = (int) QuotationRevision::query()->where('quotation_id', $quotation->getKey())->max('revision_number') + 1;
            $revision = $this->storeRevision($quotation, $nextNumber, $actor, $prepared);
            $quotation->current_revision_id = $revision->getKey();
            $quotation->status = QuotationStatus::DRAFT;
            $quotation->save();
            $this->audit->record('quotation_revision_created', $quotation, newValues: [
                ...$this->auditPayload($quotation, $revision),
                'previous_revision_number' => $previous->revision_number,
                'previous_status' => $previous->stringAttribute('status'),
            ], actor: $actor);

            return $quotation;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{lines: list<array{position: int, description: string, unit: string, quantity: string, unit_price: string, line_total: string}>, attributes: array<string, mixed>}
     */
    private function prepare(Party $party, array $input): array
    {
        $lines = $this->calculator->normalizeLines($this->lineInput($input));
        [$quotationDate, $validUntil] = $this->dates($input);

        return [
            'lines' => $lines,
            'attributes' => [
                ...$this->snapshots->capture($party, $input),
                'quotation_date' => $quotationDate,
                'valid_until' => $validUntil,
                'currency_code' => Money::DEFAULT_CURRENCY,
                'terms' => $this->nullableText($input, 'terms', 5000),
                'customer_notes' => $this->nullableText($input, 'customer_notes', 5000),
                'internal_notes' => $this->nullableText($input, 'internal_notes', 5000),
                'tax_notice' => QuotationCalculator::taxNotice(),
                'tax_treatment' => 'excluded',
                'subtotal' => $this->calculator->sum(array_column($lines, 'line_total')),
                'total' => $this->calculator->sum(array_column($lines, 'line_total')),
            ],
        ];
    }

    /**
     * @param  array{lines: list<array{position: int, description: string, unit: string, quantity: string, unit_price: string, line_total: string}>, attributes: array<string, mixed>}  $prepared
     */
    private function storeRevision(Quotation $quotation, int $number, User $actor, array $prepared): QuotationRevision
    {
        $revision = $quotation->revisions()->create([
            ...$prepared['attributes'],
            'revision_number' => $number,
            'status' => QuotationStatus::DRAFT,
            'created_by' => $actor->getKey(),
        ]);
        $this->replaceLines($revision, $prepared['lines']);

        return $revision;
    }

    /**
     * @param  list<array{position: int, description: string, unit: string, quantity: string, unit_price: string, line_total: string}>  $lines
     */
    private function replaceLines(QuotationRevision $revision, array $lines): void
    {
        if ($revision->stringAttribute('status') !== QuotationStatus::DRAFT) {
            throw new QuotationRuleException('lines', 'Issued lines cannot be replaced.');
        }

        foreach ($revision->lines()->get() as $existing) {
            if ($existing->delete() !== true) {
                throw new QuotationRuleException('lines', 'Draft lines could not be replaced.');
            }
        }

        foreach ($lines as $line) {
            $revision->lines()->create($line);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: string, 1: string}
     */
    private function dates(array $input): array
    {
        $quotationDate = $this->date($input['quotation_date'] ?? null, 'quotation_date');
        $validUntil = $this->date($input['valid_until'] ?? null, 'valid_until');

        if ($validUntil < $quotationDate) {
            throw new QuotationRuleException('valid_until', 'The validity date cannot be before the quotation date.');
        }

        $latestQuotation = now()->timezone('Asia/Dhaka')->addYear()->toDateString();

        if ($quotationDate < '2000-01-01' || $quotationDate > $latestQuotation) {
            throw new QuotationRuleException('quotation_date', 'The quotation date is outside the allowed range.');
        }

        $latestValid = Carbon::parse($quotationDate, 'Asia/Dhaka')->addYears(2)->toDateString();

        if ($validUntil > $latestValid) {
            throw new QuotationRuleException('valid_until', 'Validity cannot be more than two years after the quotation date.');
        }

        return [$quotationDate, $validUntil];
    }

    private function date(mixed $value, string $field): string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw new QuotationRuleException($field, 'Enter a date as YYYY-MM-DD.');
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (! $parsed instanceof \DateTimeImmutable || $parsed->format('Y-m-d') !== $value) {
            throw new QuotationRuleException($field, 'Enter a real date.');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array<string, mixed>>
     */
    private function lineInput(array $input): array
    {
        $lines = $input['lines'] ?? null;

        if (! is_array($lines)) {
            throw new QuotationRuleException('lines', 'Add at least one line.');
        }

        $result = [];

        foreach ($lines as $line) {
            if (is_array($line)) {
                $result[] = $line;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function nullableText(array $input, string $key, int $max): ?string
    {
        $value = $input[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if (mb_strlen($trimmed) > $max) {
            throw new QuotationRuleException($key, 'This text is too long.');
        }

        return $trimmed;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function integer(array $input, string $key): int
    {
        $value = $input[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1) {
            return (int) $value;
        }

        throw new QuotationRuleException($key, 'Choose a customer.');
    }

    /**
     * @return array<string, mixed>
     */
    private function auditPayload(Quotation $quotation, QuotationRevision $revision): array
    {
        return [
            'number' => $quotation->stringAttribute('number'),
            'revision_number' => $revision->revision_number,
            'revision_id' => $revision->getKey(),
            'party_id' => $quotation->party_id,
            'party_role' => $quotation->stringAttribute('party_role'),
            'status' => $revision->stringAttribute('status'),
            'total' => $revision->decimalAttribute('total'),
            'currency_code' => $revision->stringAttribute('currency_code'),
            'tax_treatment' => $revision->stringAttribute('tax_treatment'),
            'line_count' => $revision->lines()->count(),
        ];
    }
}

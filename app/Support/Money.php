<?php

namespace App\Support;

use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Currency amount stored at poisha scale (2 decimal places).
 *
 * Rounding of a tie is half away from zero. This is an application rule.
 * It is not a claim about NBR VAT rounding, which must be confirmed before
 * tax documents are issued.
 */
final readonly class Money
{
    public const SCALE = 2;

    public const DEFAULT_CURRENCY = 'BDT';

    private function __construct(private Decimal $amount, private string $currency) {}

    public static function of(string|int $amount, string $currency = self::DEFAULT_CURRENCY): self
    {
        $currency = strtoupper($currency);

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Currency must be a 3-letter ISO code.');
        }

        return new self(
            Decimal::of($amount)->toScale(self::SCALE, RoundingMode::HalfUp),
            $currency,
        );
    }

    public static function zero(string $currency = self::DEFAULT_CURRENCY): self
    {
        return self::of(0, $currency);
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount->plus($other->amount)->toScale(self::SCALE), $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount->minus($other->amount)->toScale(self::SCALE), $this->currency);
    }

    public function multipliedBy(string|int $factor): self
    {
        return new self(
            $this->amount->multipliedBy($factor)->toScale(self::SCALE),
            $this->currency,
        );
    }

    public function amount(): string
    {
        return (string) $this->amount->toScale(self::SCALE);
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->amount->compare($other->amount) === 0;
    }

    /**
     * Western three-digit grouping. Lakh/crore grouping is intentionally not
     * applied until the company confirms the display convention.
     */
    public function format(): string
    {
        $raw = $this->amount();
        $negative = str_starts_with($raw, '-');
        $raw = ltrim($raw, '-');
        [$whole, $fraction] = explode('.', $raw, 2);
        $wholeWithGrouping = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole) ?? $whole;
        $prefix = $this->currency === self::DEFAULT_CURRENCY ? '৳' : $this->currency.' ';

        return ($negative ? '-' : '').$prefix.$wholeWithGrouping.'.'.$fraction;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Cannot combine amounts in different currencies.');
        }
    }
}

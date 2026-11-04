<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Base-10 decimal value. Input is accepted only as an integer or a plain
 * decimal string so binary floating-point values never enter the calculation.
 */
final readonly class Decimal
{
    private function __construct(private BigDecimal $value) {}

    public static function of(string|int $value): self
    {
        if (is_int($value)) {
            return new self(BigDecimal::of($value));
        }

        $normalized = trim($value);

        if (! preg_match('/^[+-]?\d+(\.\d+)?$/', $normalized)) {
            throw new InvalidArgumentException('Decimal values must be plain base-10 numbers.');
        }

        return new self(BigDecimal::of($normalized));
    }

    public static function zero(): self
    {
        return self::of(0);
    }

    public function plus(self $other): self
    {
        return new self($this->value->plus($other->value));
    }

    public function minus(self $other): self
    {
        return new self($this->value->minus($other->value));
    }

    public function multipliedBy(self|string|int $factor): self
    {
        $factor = $factor instanceof self ? $factor->value : self::of($factor)->value;

        return new self($this->value->multipliedBy($factor));
    }

    public function dividedBy(self|string|int $divisor, int $scale, RoundingMode $rounding = RoundingMode::HalfUp): self
    {
        $divisor = $divisor instanceof self ? $divisor->value : self::of($divisor)->value;

        if ($divisor->isZero()) {
            throw new InvalidArgumentException('Cannot divide a decimal by zero.');
        }

        return new self($this->value->dividedBy($divisor, $scale, $rounding));
    }

    public function compare(self $other): int
    {
        return $this->value->compareTo($other->value);
    }

    public function isZero(): bool
    {
        return $this->value->isZero();
    }

    public function isNegative(): bool
    {
        return $this->value->isNegative();
    }

    public function toScale(int $scale, RoundingMode $rounding = RoundingMode::HalfUp): self
    {
        return new self($this->value->toScale($scale, $rounding));
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}

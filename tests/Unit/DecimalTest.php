<?php

namespace Tests\Unit;

use App\Accounting\JournalBalance;
use App\Support\Decimal;
use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DecimalTest extends TestCase
{
    public function test_addition_does_not_use_binary_floating_point(): void
    {
        $sum = Decimal::of('0.10')->plus(Decimal::of('0.20'));

        $this->assertSame('0.30', (string) $sum);
        $this->assertSame(0, $sum->compare(Decimal::of('0.30')));
    }

    public function test_money_keeps_poisha_scale(): void
    {
        $total = Money::of('0.10')->plus(Money::of('0.20'));

        $this->assertSame('0.30', $total->amount());
        $this->assertSame('BDT', $total->currency());
        $this->assertSame('৳0.30', $total->format());
    }

    public function test_money_rounds_half_away_from_zero(): void
    {
        $this->assertSame('10.01', Money::of('10.005')->amount());
        $this->assertSame('10.00', Money::of('10.004')->amount());
        $this->assertSame('-10.01', Money::of('-10.005')->amount());
    }

    public function test_money_rejects_a_foreign_currency_mix(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of('1.00', 'BDT')->plus(Money::of('1.00', 'USD'));
    }

    public function test_decimal_rejects_floats_written_as_scientific_notation(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Decimal::of('1e-1');
    }

    public function test_division_by_zero_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Decimal::of('10')->dividedBy('0', 2);
    }

    #[DataProvider('balancedJournals')]
    public function test_journal_balance_accepts_equal_sides(array $debits, array $credits): void
    {
        $this->assertTrue(JournalBalance::isBalanced($debits, $credits));
    }

    public function test_journal_balance_rejects_a_penny_difference_and_negative_amounts(): void
    {
        $this->assertFalse(JournalBalance::isBalanced(['0.10', '0.20'], ['0.31']));

        $this->expectException(InvalidArgumentException::class);

        JournalBalance::isBalanced(['-1.00'], ['-1.00']);
    }

    /**
     * @return array<string, array{0: list<string>, 1: list<string>}>
     */
    public static function balancedJournals(): array
    {
        return [
            'poisha' => [['0.10', '0.20'], ['0.30']],
            'large' => [['1000000.10', '0.20'], ['1000000.30']],
        ];
    }
}

<?php

namespace App\Units;

/**
 * Starter measurement units. The owner can rename, deactivate, or delete them.
 * A line stores the symbol as text, so changing this list does not rewrite history.
 */
final class UnitCatalog
{
    /**
     * @return list<array{name: string, symbol: string, sort_order: int}>
     */
    public static function starter(): array
    {
        return [
            ['name' => 'Pieces', 'symbol' => 'pcs', 'sort_order' => 10],
            ['name' => 'Kilogram', 'symbol' => 'kg', 'sort_order' => 20],
            ['name' => 'Gram', 'symbol' => 'g', 'sort_order' => 30],
            ['name' => 'Litre', 'symbol' => 'L', 'sort_order' => 40],
            ['name' => 'Millilitre', 'symbol' => 'ml', 'sort_order' => 50],
            ['name' => 'Metre', 'symbol' => 'm', 'sort_order' => 60],
            ['name' => 'Centimetre', 'symbol' => 'cm', 'sort_order' => 70],
            ['name' => 'Square foot', 'symbol' => 'sq ft', 'sort_order' => 80],
            ['name' => 'Box', 'symbol' => 'box', 'sort_order' => 90],
            ['name' => 'Set', 'symbol' => 'set', 'sort_order' => 100],
            ['name' => 'Pair', 'symbol' => 'pair', 'sort_order' => 110],
            ['name' => 'Hour', 'symbol' => 'hour', 'sort_order' => 120],
            ['name' => 'Day', 'symbol' => 'day', 'sort_order' => 130],
            ['name' => 'Job / lot', 'symbol' => 'job', 'sort_order' => 140],
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'symbol',
    'is_active',
    'sort_order',
])]
class Unit extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Active units offered on a new line. An inactive unit stays out of this list.
     *
     * @return list<array{name: string, symbol: string}>
     */
    public static function choices(): array
    {
        return self::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['name', 'symbol'])
            ->map(fn (self $unit): array => [
                'name' => $unit->name,
                'symbol' => $unit->symbol,
            ])
            ->all();
    }
}

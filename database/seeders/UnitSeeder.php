<?php

namespace Database\Seeders;

use App\Models\Unit;
use App\Units\UnitCatalog;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        if (Unit::query()->exists()) {
            return;
        }

        $now = now();

        Unit::query()->insert(array_map(
            fn (array $unit): array => [
                'name' => $unit['name'],
                'symbol' => $unit['symbol'],
                'is_active' => true,
                'sort_order' => $unit['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
            UnitCatalog::starter(),
        ));
    }
}

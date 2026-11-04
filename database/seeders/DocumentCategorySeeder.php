<?php

namespace Database\Seeders;

use App\Company\CompanyModuleInstaller;
use Illuminate\Database\Seeder;

class DocumentCategorySeeder extends Seeder
{
    public function run(): void
    {
        app(CompanyModuleInstaller::class)->syncCategories();
    }
}

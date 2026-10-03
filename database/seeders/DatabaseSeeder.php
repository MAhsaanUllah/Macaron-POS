<?php

namespace Database\Seeders;

use App\Models\SystemConfig;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        SystemConfig::firstOrCreate([], [
            'business_type' => 'sweets',
        ]);

        $this->call(SweetShopSeeder::class);
    }
}

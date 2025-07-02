<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TierLevel;

class TierLevelSeeder extends Seeder
{
    public function run(): void
    {
        TierLevel::create([
            'name' => 'Green Seed',
            'point_from' => 0,
            'point_to' => 1000,
            'multiplier' => 1.0,
            'description' => 'Starting tier for new recyclers',
        ]);
    }
}
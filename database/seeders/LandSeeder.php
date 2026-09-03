<?php

namespace Database\Seeders;

use App\Models\Land;
use App\Models\Village;
use Illuminate\Database\Seeder;

class LandSeeder extends Seeder
{
    public function run(): void
    {
        $village1 = Village::where('code', 'V-01')->first();

        $lands = [
            ['village_id' => $village1->id ?? 1, 'code' => 'L-01', 'name' => 'Lahan Hutan Lindung', 'area' => 150.5, 'latitude' => -6.9167, 'longitude' => 107.6167, 'status' => 'active'],
            ['village_id' => $village1->id ?? 1, 'code' => 'L-02', 'name' => 'Lahan Kritis Babakan', 'area' => 75.2, 'latitude' => -6.9150, 'longitude' => 107.6180, 'status' => 'active'],
        ];

        foreach ($lands as $land) {
            Land::updateOrCreate(['code' => $land['code']], $land);
        }
    }
}

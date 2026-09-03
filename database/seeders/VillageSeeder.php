<?php

namespace Database\Seeders;

use App\Models\Village;
use App\Models\District;
use Illuminate\Database\Seeder;

class VillageSeeder extends Seeder
{
    public function run(): void
    {
        $district1 = District::where('code', 'D-01')->first();

        $villages = [
            ['district_id' => $district1->id ?? 1, 'code' => 'V-01', 'name' => 'Babakan Ciamis', 'latitude' => -6.9167, 'longitude' => 107.6167],
            ['district_id' => $district1->id ?? 1, 'code' => 'V-02', 'name' => 'Braga', 'latitude' => -6.9167, 'longitude' => 107.6167],
        ];

        foreach ($villages as $village) {
            Village::updateOrCreate(['code' => $village['code']], $village);
        }
    }
}

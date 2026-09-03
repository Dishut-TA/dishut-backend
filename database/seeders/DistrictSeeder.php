<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\City;
use Illuminate\Database\Seeder;

class DistrictSeeder extends Seeder
{
    public function run(): void
    {
        $city1 = City::where('code', 'C-01')->first();
        $city2 = City::where('code', 'C-02')->first();

        $districts = [
            ['city_id' => $city1->id ?? 1, 'code' => 'D-01', 'name' => 'Sumur Bandung', 'latitude' => -6.9167, 'longitude' => 107.6167],
            ['city_id' => $city1->id ?? 1, 'code' => 'D-02', 'name' => 'Coblong', 'latitude' => -6.8833, 'longitude' => 107.6167],
            ['city_id' => $city2->id ?? 2, 'code' => 'D-03', 'name' => 'Soreang', 'latitude' => -7.0333, 'longitude' => 107.5167],
        ];

        foreach ($districts as $district) {
            District::updateOrCreate(['code' => $district['code']], $district);
        }
    }
}

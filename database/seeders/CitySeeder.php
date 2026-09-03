<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            ['code' => 'C-01', 'name' => 'Kota Bandung', 'latitude' => -6.914744, 'longitude' => 107.609810],
            ['code' => 'C-02', 'name' => 'Kabupaten Bandung', 'latitude' => -7.025253, 'longitude' => 107.525853],
        ];

        foreach ($cities as $city) {
            City::updateOrCreate(['code' => $city['code']], $city);
        }
    }
}

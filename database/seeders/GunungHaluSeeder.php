<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\District;
use App\Models\Village;
use Illuminate\Database\Seeder;

class GunungHaluSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat Kabupaten
        $city = City::firstOrCreate(
            ['code' => 'C-BB'],
            ['name' => 'Kabupaten Bandung Barat', 'latitude' => -6.8375, 'longitude' => 107.508]
        );

        // 2. Buat Kecamatan
        $district = District::firstOrCreate(
            ['code' => 'D-GH'],
            ['name' => 'Gunung Halu', 'city_id' => $city->id]
        );

        // 3. Buat 4 Desa dengan Poligon (GeoJSON geometry)
        // Kita bagi Bounding Box Gunung Halu (107.30, -7.05) s.d (107.45, -6.95) menjadi 4 kuadran
        $villages = [
            [
                'name' => 'Desa Celak',
                'code' => 'V-GH-01',
                'lat'  => -7.025,
                'lng'  => 107.3375,
                'geom' => $this->makePolygon(107.30, -7.05, 107.375, -7.00)
            ],
            [
                'name' => 'Desa Sirnajaya',
                'code' => 'V-GH-02',
                'lat'  => -7.025,
                'lng'  => 107.4125,
                'geom' => $this->makePolygon(107.375, -7.05, 107.45, -7.00)
            ],
            [
                'name' => 'Desa Sindangjaya',
                'code' => 'V-GH-03',
                'lat'  => -6.975,
                'lng'  => 107.3375,
                'geom' => $this->makePolygon(107.30, -7.00, 107.375, -6.95)
            ],
            [
                'name' => 'Desa Wargasaluyu',
                'code' => 'V-GH-04',
                'lat'  => -6.975,
                'lng'  => 107.4125,
                'geom' => $this->makePolygon(107.375, -7.00, 107.45, -6.95)
            ]
        ];

        foreach ($villages as $v) {
            Village::updateOrCreate(
                ['code' => $v['code']],
                [
                    'district_id' => $district->id,
                    'name'        => $v['name'],
                    'latitude'    => $v['lat'],
                    'longitude'   => $v['lng'],
                    'geometry'    => $v['geom'],
                ]
            );
        }
    }

    private function makePolygon($minX, $minY, $maxX, $maxY)
    {
        $geojson = [
            'type' => 'Polygon',
            'coordinates' => [
                [
                    [$minX, $minY],
                    [$maxX, $minY],
                    [$maxX, $maxY],
                    [$minX, $maxY],
                    [$minX, $minY]
                ]
            ]
        ];
        return json_encode($geojson);
    }
}

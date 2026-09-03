<?php

namespace Database\Seeders;

use App\Models\Seed;
use Illuminate\Database\Seeder;

class SeedSeeder extends Seeder
{
    public function run(): void
    {
        $seeds = [
            ['code' => 'S-01', 'name' => 'Bibit Mahoni', 'type' => 'Kayu', 'category' => 'Hutan Produksi', 'description' => 'Bibit pohon mahoni kualitas unggul', 'status' => 'active'],
            ['code' => 'S-02', 'name' => 'Bibit Jati', 'type' => 'Kayu', 'category' => 'Hutan Produksi', 'description' => 'Bibit pohon jati super', 'status' => 'active'],
            ['code' => 'S-03', 'name' => 'Bibit Mangrove', 'type' => 'Pesisir', 'category' => 'Hutan Lindung', 'description' => 'Bibit mangrove untuk penahan abrasi', 'status' => 'active'],
        ];

        foreach ($seeds as $seed) {
            Seed::updateOrCreate(['code' => $seed['code']], $seed);
        }
    }
}

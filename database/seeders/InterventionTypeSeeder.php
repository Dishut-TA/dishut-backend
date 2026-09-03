<?php

namespace Database\Seeders;

use App\Models\InterventionType;
use Illuminate\Database\Seeder;

class InterventionTypeSeeder extends Seeder
{
    public function run()
    {
        InterventionType::create(['code' => 'JI-001', 'name' => 'Reboisasi', 'description' => 'Penanaman kembali hutan yang gundul', 'status' => 'active']);
        InterventionType::create(['code' => 'JI-002', 'name' => 'Agroforestri', 'description' => 'Sistem wanatani untuk optimalisasi lahan', 'status' => 'active']);
    }
}

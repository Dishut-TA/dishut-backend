<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterKomoditasRhlSeeder extends Seeder
{
 public function run(): void
 {
  DB::table('master_komoditas_rhl')->insert([
   [
    'nama_tanaman'=>'Sengon',
    'jenis'=>'Kayu',
    'min_elevation'=>0,
    'max_elevation'=>1600,
    'min_slope'=>0,
    'max_slope'=>40,
    'min_rainfall'=>2000,
    'max_rainfall'=>4000,
    'soil_match'=>'Latosol,Andosol'
   ]
  ]);
 }
}

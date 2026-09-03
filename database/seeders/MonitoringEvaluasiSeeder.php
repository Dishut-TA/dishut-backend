<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Penugasan;
use App\Models\DonationProgram;
use App\Models\PetakUkur;
use App\Models\DataTanaman;

class MonitoringEvaluasiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            'Siap Monitoring',
            'Dalam Monitoring',
            'Menunggu Evaluasi',
            'Tindak Lanjut',
            'Monitoring Selesai',
            'Dihentikan'
        ];

        // Find some existing DonationPrograms
        $programs = DonationProgram::take(6)->get();

        foreach ($statuses as $index => $status) {
            if (!isset($programs[$index])) continue;
            
            $program = $programs[$index];

            // 1. Create a Pelaksanaan Penanaman penugasan
            $pelaksanaan = Penugasan::create([
                'penugasanable_type' => DonationProgram::class,
                'penugasanable_id' => $program->id,
                'penyuluh_id' => 3, // Asumsi 3 adalah ID penyuluh
                'jenis_kegiatan' => 'Pelaksanaan Penanaman',
                'status' => 'Selesai',
                'tanggal_penugasan' => now()->subMonths(3),
                'batas_waktu' => now()->subMonths(2),
            ]);

            // Create Petak Ukur for it
            $pu1 = PetakUkur::create([
                'penugasan_id' => $pelaksanaan->id,
                'nama' => 'PU-01',
                'luas' => 1.5,
                'polygon_data' => json_encode(['type' => 'Polygon']),
                'status' => 'Selesai'
            ]);

            $pu2 = PetakUkur::create([
                'penugasan_id' => $pelaksanaan->id,
                'nama' => 'PU-02',
                'luas' => 1.5,
                'polygon_data' => json_encode(['type' => 'Polygon']),
                'status' => 'Selesai'
            ]);

            // Add DataTanaman based on the status we want to simulate
            // Statuses: 'Siap Monitoring', 'Dalam Monitoring', 'Menunggu Evaluasi', 'Tindak Lanjut', 'Monitoring Selesai', 'Dihentikan'
            
            // To simulate 'Tindak Lanjut', we need PU failure (<75%)
            $jumlahHidupPU1 = ($status === 'Tindak Lanjut') ? 20 : 90; 
            
            DataTanaman::create([
                'petak_ukur_id' => $pu1->id,
                'nama_tanaman' => 'Mangrove',
                'jumlah' => $jumlahHidupPU1,
                'kondisi_tanaman' => 'Hidup',
                'keterangan' => 'Kondisi baik'
            ]);

            DataTanaman::create([
                'petak_ukur_id' => $pu1->id,
                'nama_tanaman' => 'Mangrove',
                'jumlah' => 10,
                'kondisi_tanaman' => 'Mati',
                'keterangan' => 'Mati terkena hama'
            ]);

            DataTanaman::create([
                'petak_ukur_id' => $pu2->id,
                'nama_tanaman' => 'Mangrove',
                'jumlah' => 95,
                'kondisi_tanaman' => 'Sehat',
                'keterangan' => 'Tumbuh lebat'
            ]);

            // 2. Create the Monitoring penugasan
            Penugasan::create([
                'penugasanable_type' => DonationProgram::class,
                'penugasanable_id' => $program->id,
                'penyuluh_id' => 3, 
                'jenis_kegiatan' => 'Monitoring',
                'status' => $status,
                'tanggal_penugasan' => now()->subDays(5),
                'batas_waktu' => now()->addDays(10),
                'periode_monitoring' => 'P1',
            ]);
        }
    }
}

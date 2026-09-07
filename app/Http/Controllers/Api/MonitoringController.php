<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\FieldValidation;
use App\Models\AnalysisResultZone;
use App\Models\User;

class MonitoringController extends Controller
{
    /**
     * GET /api/monitoring/dashboard
     * Menarik data statistik dan koordinat peta untuk Dashboard Monitoring
     */
    public function dashboard(): JsonResponse
    {
        // Statistik (bisa dikembangkan sesuai kebutuhan)
        $totalKegiatan = FieldValidation::count();
        $selesai = FieldValidation::where('status_verifikasi', 'Terima')->count();
        $berjalan = FieldValidation::where('status_verifikasi', 'Belum')->count();
        $bermasalah = FieldValidation::where('status_verifikasi', 'Tolak')->count();
        
        $totalLaporan = $totalKegiatan; // Simulasi: jumlah laporan = jumlah validasi
        
        // Asumsi penyuluh adalah user dengan role penyuluh (di sini simulasi query distinct)
        $penyuluhAktif = FieldValidation::distinct('nama_penyuluh')->count('nama_penyuluh');
        
        // Wilayah CDK
        $totalWilayahCdk = AnalysisResultZone::distinct('cdk')->count('cdk');
        if ($totalWilayahCdk == 0) $totalWilayahCdk = 3; // Fallback untuk tampilan kosong
        
        $totalBibit = 3000; // Contoh statis, nanti bisa dikonek ke tabel planted_seeds

        // Jika data benar-benar kosong, kita bisa mengirimkan dummy untuk keperluan demonstrasi
        $isDummy = false;
        $validations = FieldValidation::with('zone')->get();
        
        // Sumber utama titik peta adalah petak ukur yang digambar penyuluh
        // (petak_ukurs.polygon_data). field_validations hanya dipakai sebagai
        // cadangan bila belum ada satu pun petak ukur berkoordinat, dan data
        // dummy hanya muncul bila kedua sumber kosong.
        $mapMarkers = \App\Support\PetaPetakUkur::titik();

        if (empty($mapMarkers) && $validations->isNotEmpty()) {
            foreach ($validations as $val) {
                if ($val->titik_koordinat_gps) {
                    $coords = explode(',', $val->titik_koordinat_gps);
                    if (count($coords) >= 2) {
                        $lat = (float) trim($coords[0]);
                        $lng = (float) trim($coords[1]);
                        
                        $status = 'berjalan';
                        if ($val->status_verifikasi === 'Terima') $status = 'selesai';
                        if ($val->status_verifikasi === 'Tolak') $status = 'bermasalah';

                        $mapMarkers[] = [
                            'id' => $val->id,
                            'lat' => $lat,
                            'lng' => $lng,
                            'nama_lokasi' => $val->nama_lokasi ?? 'Lokasi ' . $val->id,
                            'desa' => $val->zone ? $val->zone->desa : '-',
                            'status' => $status, // berjalan, selesai, bermasalah
                            'penyuluh' => $val->nama_penyuluh,
                        ];
                    }
                }
            }
        }

        if (empty($mapMarkers)) {
            // Data dummy jika tabel kosong (agar Peta terlihat cantik saat di-demo)
            $isDummy = true;
            $mapMarkers = [
                ['id' => 1, 'lat' => -6.9204, 'lng' => 107.5046, 'nama_lokasi' => 'Gunung Halu Sektor 1', 'desa' => 'Gunung Halu', 'status' => 'berjalan', 'penyuluh' => 'Budi'],
                ['id' => 2, 'lat' => -6.8904, 'lng' => 107.6046, 'nama_lokasi' => 'Lembang Rehabilitasi', 'desa' => 'Lembang', 'status' => 'selesai', 'penyuluh' => 'Andi'],
                ['id' => 3, 'lat' => -6.9504, 'lng' => 107.7046, 'nama_lokasi' => 'Cimenyan Blok B', 'desa' => 'Cimenyan', 'status' => 'bermasalah', 'penyuluh' => 'Siti'],
            ];
            
            // Set dummy stats
            $totalKegiatan = 3;
            $selesai = 1;
            $berjalan = 1;
            $bermasalah = 1;
            $totalLaporan = 3;
            $penyuluhAktif = 3;
        }

        return response()->json([
            'message' => 'Berhasil mengambil data dashboard monitoring.',
            'is_dummy' => $isDummy,
            'stats' => [
                'jumlah_kegiatan' => $totalKegiatan,
                'kegiatan_selesai' => $selesai,
                'kegiatan_berjalan' => $berjalan,
                'kegiatan_bermasalah' => $bermasalah,
                'total_laporan' => $totalLaporan,
                'penyuluh_aktif' => $penyuluhAktif,
                'total_wilayah_cdk' => $totalWilayahCdk,
                'total_bibit_ditanam' => $totalBibit,
            ],
            'map_markers' => $mapMarkers
        ]);
    }
}

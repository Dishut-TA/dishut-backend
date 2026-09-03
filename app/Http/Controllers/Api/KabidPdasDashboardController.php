<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction;
use App\Models\ProgramCsr;
use App\Models\ProgramApbd;

class KabidPdasDashboardController extends Controller
{
    public function getKabidPdasDashboard(Request $request)
    {
        $year = $request->query('year', date('Y'));

        // ==========================================
        // 1. DATA SUMMARY CARDS
        // ==========================================
        $totalLahanCsr = ProgramCsr::whereYear('created_at', $year)->sum('target_luas_lahan');
        $totalLahanApbd = ProgramApbd::whereYear('created_at', $year)->sum('target_luas_lahan');
        $totalLahanPrioritas = $totalLahanCsr + $totalLahanApbd;
        
        // UBAH BAGIAN INI:
        // Menggunakan whereNotIn agar logikanya sama persis dengan Dashboard Donasi
        $totalDonasiTerkumpul = Transaction::whereYear('created_at', $year)
                                           ->whereNotIn('status', ['Ditolak', 'Failed'])
                                           ->sum('amount');

        // ==========================================
        // 2. DATA TREN DONASI (12 BULAN)
        // ==========================================
        // UBAH BAGIAN INI JUGA:
        $transactions = Transaction::select(
                DB::raw('MONTH(created_at) as month'),
                DB::raw('SUM(amount) as total')
            )
            ->whereYear('created_at', $year)
            ->whereNotIn('status', ['Ditolak', 'Failed'])
            ->groupBy('month')
            ->get();

        $bulanIndo = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Juli', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
        $trendDonasi = [];

        for ($i = 0; $i < 12; $i++) {
            $trendDonasi[] = [
                'month' => $bulanIndo[$i],
                'value' => 0
            ];
        }

        foreach ($transactions as $t) {
            $trendDonasi[$t->month - 1]['value'] = (int) $t->total;
        }

        $danaApbd = ProgramApbd::whereYear('created_at', $year)->sum('anggaran');
        $danaCsr = ProgramCsr::whereYear('created_at', $year)->sum('anggaran');

        $perbandinganPendanaan = [
            [ 'name' => 'APBD', 'value' => (int) $danaApbd, 'fill' => '#86efac' ],
            [ 'name' => 'CSR', 'value' => (int) $danaCsr, 'fill' => '#85643a' ]
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_lahan_prioritas' => $totalLahanPrioritas,
                    'total_donasi_terkumpul' => $totalDonasiTerkumpul,
                    'total_lahan_csr' => $totalLahanCsr,
                    'total_lahan_apbd' => $totalLahanApbd,
                ],
                'trend_donasi' => $trendDonasi,
                'perbandingan_pendanaan' => $perbandinganPendanaan
            ]
        ]);
    }
}
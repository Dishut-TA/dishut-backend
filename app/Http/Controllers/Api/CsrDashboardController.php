<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TransaksiCsr;
use App\Models\ProgramCsr;

class CsrDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $csr = $user->getOrCreateCsr();

        // 1. Total dana disalurkan
        $totalDanaDisalurkan = TransaksiCsr::where('csr_id', $csr->id)
            ->where('status', 'Dibayar')
            ->sum('nominal');

        // 2. KTH binaan dibantu
        $kthBinaanDibantu = ProgramCsr::whereHas('transaksiCsrs', function($q) use ($csr) {
                $q->where('csr_id', $csr->id)->where('status', 'Dibayar');
            })
            ->distinct('kth_id')
            ->count('kth_id');

        // 3. Luas rehabilitasi hijau (hektar)
        $luasRehabilitasiHijau = ProgramCsr::whereHas('transaksiCsrs', function($q) use ($csr) {
                $q->where('csr_id', $csr->id)->where('status', 'Dibayar');
            })
            ->sum('target_luas_lahan');

        // 4. Proyek Aktif
        $activeProjects = ProgramCsr::with(['kth', 'analysisResultZone'])
            ->whereHas('transaksiCsrs', function($q) use ($csr) {
                $q->where('csr_id', $csr->id)->where('status', 'Dibayar');
            })
            ->whereNotIn('status', ['Ditolak', 'Dihentikan'])
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'company_name' => $user->username,
                'email' => $user->email,
                'total_dana_disalurkan' => $totalDanaDisalurkan,
                'kth_binaan_dibantu' => $kthBinaanDibantu,
                'luas_rehabilitasi_hijau' => $luasRehabilitasiHijau,
                'active_projects' => $activeProjects
            ]
        ]);
    }
}

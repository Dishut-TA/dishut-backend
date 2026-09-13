<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProgramCsr;
use App\Models\ProgramApbd;

class KthDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $kth_id = $user->kth_id; 
        
        $apbd_aktif = ProgramApbd::where('kth_id', $kth_id)
            ->whereNotIn('status', ['Ditolak', 'Dihentikan', 'Selesai'])
            ->count();
            
        $csr_diproses = ProgramCsr::where('kth_id', $kth_id)
            ->whereIn('status', ['Menunggu Persetujuan', 'Tinjauan Dinas'])
            ->count();

        $programs = collect();
        $program_apbd = ProgramApbd::where('kth_id', $kth_id)->get();
        foreach($program_apbd as $apbd) {
            $programs->push([
                'id' => 'APBD-'.$apbd->id,
                'judulUsaha' => $apbd->nama_program,
                'skema' => 'APBD',
                'status' => $apbd->status
            ]);
        }
        
        $program_csr = ProgramCsr::where('kth_id', $kth_id)->get();
        foreach($program_csr as $csr) {
            $programs->push([
                'id' => 'CSR-'.$csr->id,
                'judulUsaha' => $csr->nama_program,
                'skema' => 'CSR',
                'status' => $csr->status
            ]);
        }
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'apbd_aktif' => $apbd_aktif,
                'csr_diproses' => $csr_diproses,
                'programs' => $programs
            ]
        ]);
    }
}

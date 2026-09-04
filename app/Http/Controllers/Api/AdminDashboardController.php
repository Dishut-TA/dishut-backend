<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DonationProgram;
use App\Models\Donation;
use App\Http\Resources\DonationProgramResource; 

class AdminDashboardController extends Controller
{
    public function index()
    {
        $menungguVerifikasi = Donation::where('seed_status', 'Pending')->count();

        $programs = DonationProgram::with(['donations', 'plantedSeeds'])->get();

        $totalCollected = $programs->sum(function($program) {
            return $program->donations->whereIn('seed_status', ['Terkumpul', 'Disalurkan', 'Terealisasi'])->sum(function($d) {
                return collect($d->seed_details)->sum('quantity');
            });
        });

        $totalRealized = $programs->sum(function($program) {
            return $program->plantedSeeds->sum('planted_quantity');
        });

        $bibitSiapSalur = max(0, $totalCollected - $totalRealized); 
        $totalTertanam = $totalRealized;

        $programAktif = DonationProgram::where('status', 'Aktif')->count();

        $donaturPending = Donation::with(['donor', 'donationProgram'])
            ->where('seed_status', 'Pending')->latest()->get();

        $progressProgram = DonationProgramResource::collection($programs);

        return response()->json([
            'data' => [
                'menunggu_verifikasi' => $menungguVerifikasi,
                'bibit_siap_salur' => $bibitSiapSalur,
                'total_bibit_tertanam' => $totalTertanam,
                'program_aktif' => $programAktif,
                'donatur_butuh_verifikasi' => $donaturPending,
                'progress_program' => $progressProgram,
            ]
        ]);
    }
}



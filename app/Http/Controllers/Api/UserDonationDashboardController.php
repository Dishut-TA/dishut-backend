<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Donation;
use App\Models\Donor;

class UserDonationDashboardController extends Controller
{
    public function index(Request $request)
    {
        $donorIds = Donor::where('user_id', $request->user()->id)->pluck('id');

        if ($donorIds->isEmpty()) {
            return response()->json([
                'data' => [
                    'stats' => ['total_donasi' => 0, 'terealisasi' => 0, 'diproses' => 0],
                    'recent_statuses' => []
                ]
            ]);
        }

        $totalDonasi = Donation::whereIn('donor_id', $donorIds)
            ->whereNotIn('seed_status', ['Ditolak', 'Batal'])
            ->sum('seed_quantity');

        $terealisasi = Donation::whereIn('donor_id', $donorIds)
            ->where('seed_status', 'Terealisasi')
            ->sum('seed_quantity');

        $diproses = Donation::whereIn('donor_id', $donorIds)
            ->whereIn('seed_status', ['Pending', 'Terkumpul', 'Disalurkan'])
            ->sum('seed_quantity');

        $recentDonations = Donation::with(['seed', 'donationProgram'])
            ->whereIn('donor_id', $donorIds)
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($donation) {
                $namaBibit = $donation->seed->nama ?? $donation->seed->name ?? 'Pohon';
                
                return [
                    'id' => $donation->id,
                    'title' => $donation->seed_quantity . ' ' . $namaBibit,
                    'program' => $donation->donationProgram->name ?? 'Program Umum',
                    'status' => $donation->seed_status,
                ];
            });

        return response()->json([
            'data' => [
                'stats' => [
                    'total_donasi' => (int) $totalDonasi,
                    'terealisasi' => (int) $terealisasi,
                    'diproses' => (int) $diproses,
                ],
                'recent_statuses' => $recentDonations
            ]
        ]);
    }
}
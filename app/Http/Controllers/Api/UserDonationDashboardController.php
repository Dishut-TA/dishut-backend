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

        $allDonations = Donation::whereIn('donor_id', $donorIds)->get();

        $totalDonasi = $allDonations->whereNotIn('seed_status', ['Ditolak', 'Batal'])
            ->sum(fn($d) => collect($d->seed_details)->sum('quantity'));

        $terealisasi = $allDonations->where('seed_status', 'Terealisasi')
            ->sum(fn($d) => collect($d->seed_details)->sum('quantity'));

        $diproses = $allDonations->whereIn('seed_status', ['Pending', 'Terkumpul', 'Disalurkan'])
            ->sum(fn($d) => collect($d->seed_details)->sum('quantity'));

        $recentDonations = Donation::with(['donationProgram'])
            ->whereIn('donor_id', $donorIds)
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($donation) {
                $details = collect($donation->seed_details);
                $totalQuantity = $details->sum('quantity');
                $namaBibit = $details->pluck('name')->filter()->join(', ') ?: 'Bibit';
                
                return [
                    'id' => $donation->id,
                    'title' => $totalQuantity . ' Bibit (' . $namaBibit . ')',
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

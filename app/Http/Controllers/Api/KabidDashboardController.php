<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\PlantedSeed;
use App\Models\Report; 
use Carbon\Carbon;

class KabidDashboardController extends Controller
{
    public function index()
    {
        $totalDonasi = Transaction::whereNotIn('status', ['Ditolak', 'Failed'])->sum('amount');

        $totalTerealisasi = PlantedSeed::sum('planted_quantity');

        $chartData = collect();
        for ($i = 4; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            
            $totalBulanIni = PlantedSeed::whereYear('created_at', $date->year)
                                        ->whereMonth('created_at', $date->month)
                                        ->sum('planted_quantity');
            
            $chartData->push([
                'name' => $date->translatedFormat('M'), 
                'bibit' => (int) $totalBulanIni
            ]);
        }

        $recentReports = [];
        if (class_exists(\App\Models\Report::class)) {
            $reports = \App\Models\Report::latest()->take(3)->get();
            foreach ($reports as $report) {
                $recentReports[] = [
                    'id' => $report->id,
                    'title' => $report->title ?? 'Laporan Kegiatan',
                    'date' => $report->created_at->translatedFormat('d M Y'),
                    'author' => $report->author ?? 'Staff PDAS'
                ];
            }
        }

        return response()->json([
            'data' => [
                'total_donasi' => $totalDonasi,
                'total_terealisasi' => $totalTerealisasi,
                'chart_data' => $chartData,
                'recent_reports' => $recentReports,
            ]
        ]);
    }
}
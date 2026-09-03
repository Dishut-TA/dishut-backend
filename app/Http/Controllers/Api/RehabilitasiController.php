<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AnalysisResultZone;
use Illuminate\Http\JsonResponse;

class RehabilitasiController extends Controller
{
    /**
     * GET /api/rehabilitasi/valid-zones
     * Fetch zones that are marked as 'Valid' by Kabid PDAS, to be shown to Staff PDAS.
     */
    public function getValidZones(): JsonResponse
    {
        $zones = AnalysisResultZone::with(['result.project', 'fieldValidations'])
            ->whereIn('status_kelayakan', ['Valid', 'Layak', 'Tidak Valid', 'Tidak Layak'])
            ->orderBy('updated_at', 'desc')
            ->get();

        foreach ($zones as $zone) {
            if ($zone->nama_kelompok) {
                $query = \App\Models\Kth::where('nama', $zone->nama_kelompok);
                
                if ($zone->ketua_kelompok && $zone->ketua_kelompok !== '-') {
                    $query->where('ketua', $zone->ketua_kelompok);
                }
                
                $kth = $query->first();
                
                // Fallback jika tidak ketemu dengan nama dan ketua secara spesifik
                if (!$kth) {
                    $kth = \App\Models\Kth::where('nama', $zone->nama_kelompok)->first();
                }

                if ($kth) {
                    $zone->kth_id = $kth->id;
                    $zone->nama_kelompok = $kth->nama;
                    $zone->ketua_kelompok = $kth->ketua;
                }
            }
        }

        return response()->json([
            'data' => $zones
        ]);
    }

    /**
     * POST /api/rehabilitasi/submit/{zoneId}
     * Staff PDAS inputs rehabilitation plan for the valid zone.
     * Changes status from 'Valid' to 'Layak'.
     */
    public function submitRencana(Request $request, int $zoneId): JsonResponse
    {
        $request->validate([
            'luas_lahan_total' => 'nullable|numeric',
            'panjang_pu' => 'nullable|numeric',
            'lebar_pu' => 'nullable|numeric',
            'jumlah_pu' => 'nullable|integer',
        ]);

        $zone = AnalysisResultZone::findOrFail($zoneId);

        $zone->update([
            'luas_ha' => $request->luas_lahan_total ?? $zone->luas_ha,
            'panjang_pu' => $request->panjang_pu,
            'lebar_pu' => $request->lebar_pu,
            'jumlah_pu' => $request->jumlah_pu,
            'status_kelayakan' => 'Layak',
        ]);

        return response()->json([
            'message' => 'Rencana Rehabilitasi berhasil disubmit. Status berubah menjadi Layak.',
            'data' => $zone
        ]);
    }
}

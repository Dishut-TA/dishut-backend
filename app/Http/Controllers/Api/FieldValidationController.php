<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FieldValidation;
use App\Models\AnalysisResultZone;
use Illuminate\Http\JsonResponse;

class FieldValidationController extends Controller
{
    /**
     * GET /api/field-validations
     * Mengambil semua data validasi lapangan untuk Kabid PDAS
     */
    public function index(): JsonResponse
    {
        $validations = FieldValidation::with(['zone.result.project'])
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json([
            'data' => $validations
        ]);
    }

    /**
     * POST /api/field-validations
     * Penyuluh menginput data validasi lapangan.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'zone_id' => 'required|exists:analysis_result_zones,id',
            'nama_lokasi' => 'required|string|max:255',
            'nama_penyuluh' => 'required|string|max:150',
            'kondisi_lahan' => 'nullable|string',
            'kondisi_vegetasi' => 'nullable|string',
            'kendala_lapangan' => 'nullable|string',
            'titik_koordinat_gps' => 'nullable|string',
            'foto' => 'nullable|image|max:10240', // 10MB limit
            'foto_lokasi_url' => 'nullable|string', // Fallback if using string
            'catatan_peninjauan' => 'nullable|string',
        ]);

        $fotoUrl = $request->input('foto_lokasi_url');
        
        \Illuminate\Support\Facades\Log::info('FieldValidation Store Request:', [
            'all' => $request->all(),
            'has_file_foto' => $request->hasFile('foto'),
            'file_foto' => $request->file('foto'),
        ]);

        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('field_validations', 'public');
            $fotoUrl = url('storage/' . $path);
        }

        $validation = FieldValidation::create([
            'zone_id' => $request->zone_id,
            'nama_lokasi' => $request->nama_lokasi,
            'sumber_lokasi' => 'Analisis CPI',
            'nama_penyuluh' => $request->nama_penyuluh,
            'kondisi_lahan' => $request->kondisi_lahan,
            'kondisi_vegetasi' => $request->kondisi_vegetasi,
            'kendala_lapangan' => $request->kendala_lapangan,
            'titik_koordinat_gps' => $request->titik_koordinat_gps,
            'foto_lokasi_url' => $fotoUrl,
            'catatan_peninjauan' => $request->catatan_peninjauan,
            'status_verifikasi' => 'Belum',
        ]);

        // Update status_validasi_penyuluh on zone
        $zone = AnalysisResultZone::find($request->zone_id);
        $zone->update(['status_validasi_penyuluh' => 'Sudah']);

        // Update Penugasan status to Selesai
        $penugasan = \App\Models\Penugasan::where('penugasanable_type', AnalysisResultZone::class)
            ->where('penugasanable_id', $request->zone_id)
            ->where('jenis_kegiatan', 'Validasi Lokasi')
            ->first();
        
        if ($penugasan) {
            $penugasan->update(['status' => 'Selesai']);
        }

        return response()->json([
            'message' => 'Validasi lapangan berhasil disimpan.',
            'data' => $validation
        ], 201);
    }

    /**
     * PUT /api/field-validations/{id}/verify
     * Kabid memverifikasi hasil survei (Terima/Tolak).
     */
    public function verify(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status_verifikasi' => 'required|in:Terima,Tolak'
        ]);

        $validation = FieldValidation::findOrFail($id);
        $validation->update([
            'status_verifikasi' => $request->status_verifikasi
        ]);

        // Update status_kelayakan on zone
        $zone = AnalysisResultZone::find($validation->zone_id);
        $statusKelayakan = ($request->status_verifikasi === 'Terima') ? 'Valid' : 'Tidak Valid';
        $zone->update(['status_kelayakan' => $statusKelayakan]);

        return response()->json([
            'message' => 'Verifikasi berhasil disimpan, status zona menjadi: ' . $statusKelayakan,
            'data' => $validation
        ]);
    }

    /**
     * GET /api/projects/{id}/report-rurhl
     * Generate laporan RURHL (PDF mockup) untuk zona yang 'Layak'.
     */
    public function reportRurhl(int $projectId): JsonResponse
    {
        // Untuk saat ini, mengembalikan JSON data yang akan dirender PDF di FE atau via library PDF
        $zones = AnalysisResultZone::with('fieldValidations')
            ->whereHas('result', function($q) use ($projectId) {
                $q->where('project_id', $projectId);
            })
            ->where('status_kelayakan', 'Layak')
            ->get();

        return response()->json([
            'message' => 'Data RURHL siap di-generate.',
            'total_lokasi_prioritas' => $zones->count(),
            'data' => $zones,
        ]);
    }
}

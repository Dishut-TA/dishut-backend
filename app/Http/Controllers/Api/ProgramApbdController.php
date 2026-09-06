<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProgramApbd;
use Illuminate\Http\Request;

class ProgramApbdController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(ProgramApbd::with(['kth.user', 'analysisResultZone.fieldValidations', 'analysisResultZone.result.project'])->latest()->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'kth_id'            => 'required|exists:kths,id',
            'analysis_result_zone_id' => 'nullable|exists:analysis_result_zones,id',
            'nama_program'      => 'required|string|max:255',
            'deskripsi_rencana' => 'nullable|string',
            'anggaran'          => 'required|numeric',
            'jumlah_bibit'      => 'nullable|numeric',
            'target_luas_lahan' => 'required|numeric', 
            'pilihan_intervensi' => 'nullable|string|max:255',
            'jenis_tanaman'      => 'nullable|string|max:255',
            'tanggal_mulai'     => 'nullable|date',
            'tanggal_selesai'   => 'nullable|date|after_or_equal:tanggal_mulai',
        ]);

        $validated['status'] = 'Menunggu Persetujuan';

        try {
            $program = ProgramApbd::create($validated);

            return response()->json([
                'status'  => 'success',
                'message' => 'Rancangan Program APBD berhasil dikirim ke Kepala PDAS.',
                'data'    => $program->load('kth.user')
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal membuat program APBD: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return response()->json(ProgramApbd::with(['kth.user', 'analysisResultZone.fieldValidations', 'analysisResultZone.result.project'])->findOrFail($id));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $programApbd = ProgramApbd::findOrFail($id);

        $validated = $request->validate([
            'status' => 'sometimes|string|in:Menunggu Persetujuan,Terverifikasi,Ditolak,Selesai,Aktif,Ditolak KTH',
            'nama_program'      => 'sometimes|string|max:255',
            'deskripsi_rencana' => 'sometimes|nullable|string',
            'anggaran'          => 'sometimes|numeric',
            'jumlah_bibit'      => 'sometimes|nullable|numeric',
            'target_luas_lahan' => 'sometimes|numeric',
            'pilihan_intervensi' => 'sometimes|nullable|string|max:255',
            'jenis_tanaman'      => 'sometimes|nullable|string|max:255',
            'tanggal_mulai'     => 'sometimes|nullable|date',
            'tanggal_selesai'   => 'sometimes|nullable|date',
            'analysis_result_zone_id' => 'sometimes|nullable|exists:analysis_result_zones,id',
            // ... (tambahkan validasi update lain jika perlu)
        ]);

        $programApbd->update($validated);

        return response()->json([
            'message' => 'Program APBD berhasil diupdate.',
            'data'    => $programApbd
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $programApbd = ProgramApbd::findOrFail($id);
        $programApbd->delete();

        return response()->json([
            'message' => 'Program APBD berhasil dihapus.'
        ]);
    }
}
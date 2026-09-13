<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProgramCsr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProgramCsrController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(ProgramCsr::with(['kth', 'transaksiCsrs', 'analysisResultZone.fieldValidations', 'analysisResultZone.result.project'])->latest()->get());
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
            'target_luas_lahan' => 'required|numeric',
            'jenis_tanaman'     => 'nullable|string|max:255',
            'jumlah_bibit'      => 'required|numeric',
            'anggaran'          => 'required|numeric',
            'deskripsi_rencana' => 'nullable|string',
            'file_proposal'     => 'nullable|file|mimes:pdf,doc,docx|max:5120', 
        ]);

        if ($request->hasFile('file_proposal')) {
            $path = $request->file('file_proposal')->store('proposals', 'public');
            $validated['proposal_file_path'] = $path;
        }

        $validated['status'] = 'Menunggu Verifikasi';

        try {
            $program = ProgramCsr::create($validated);

            return response()->json([
                'status'  => 'success',
                'message' => 'Pengajuan proposal CSR berhasil dikirim.',
                'data'    => $program->load('kth')
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal mengajukan proposal CSR: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return response()->json(ProgramCsr::with(['kth.user', 'transaksiCsrs', 'analysisResultZone.fieldValidations', 'analysisResultZone.result.project'])->findOrFail($id));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $programCsr = ProgramCsr::findOrFail($id);

        // PRD Feature 7: status 'Dihentikan' hanya boleh diset lewat endpoint
        // penghentian pendanaan agar penghentiannya tercatat dan merambat ke
        // modul Pelaksanaan & Monitoring.
        if ($request->input('status') === 'Dihentikan' && $programCsr->status !== 'Dihentikan') {
            return response()->json([
                'status' => 'error',
                'message' => "Status 'Dihentikan' tidak dapat diset langsung. Gunakan endpoint POST /api/program-csrs/{id}/hentikan-pendanaan.",
            ], 422);
        }

        $validated = $request->validate([
            'status' => 'sometimes|string',
            'tanggapan_perusahaan' => 'nullable|string',
            'catatan_staff' => 'nullable|string',
            'rekomendasi_mitra' => 'nullable|string',
            'rekomendasi_intervensi' => 'nullable|string',
            'nama_program'      => 'sometimes|string|max:255',
            'target_luas_lahan' => 'sometimes|numeric',
            'jenis_tanaman'     => 'nullable|string|max:255',
            'jumlah_bibit'      => 'sometimes|numeric',
            'anggaran'          => 'sometimes|numeric',
            'deskripsi_rencana' => 'nullable|string',
            'file_proposal'     => 'nullable|file|mimes:pdf,doc,docx|max:5120', 
        ]);

        if ($request->hasFile('file_proposal')) {
            if ($programCsr->proposal_file_path) {
                Storage::disk('public')->delete($programCsr->proposal_file_path);
            }
            
            $path = $request->file('file_proposal')->store('proposals', 'public');
            $validated['proposal_file_path'] = $path;
        }

        unset($validated['file_proposal']);

        $programCsr->update($validated);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Program CSR berhasil diupdate.', 
            'data' => $programCsr
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $programCsr = ProgramCsr::findOrFail($id);
        
        if ($programCsr->proposal_file_path) {
            Storage::disk('public')->delete($programCsr->proposal_file_path);
        }
        
        $programCsr->delete();

        return response()->json([
            'message' => 'Program CSR berhasil dihapus.'
        ]);
    }
}
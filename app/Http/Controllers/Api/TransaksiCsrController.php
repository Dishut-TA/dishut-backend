<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProgramCsr;
use App\Models\TransaksiCsr;
use Illuminate\Http\Request;

class TransaksiCsrController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(\App\Models\TransaksiCsr::with(['csr.user', 'programCsr.kth.user'])->get());
    }

    /**
     * Store a newly created resource in storage.
     *
     * Mencatat pendanaan mitra CSR atas sebuah program. Baris inilah yang
     * dipakai sebagai bukti kepemilikan pada Modul Investasi CSR, termasuk
     * saat mitra ingin menghentikan pendanaan (PRD Feature 7).
     *
     * csr_id selalu diambil dari user yang login, tidak dari request body.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $csr = $user ? $user->csr : null;

        if (! $csr) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akun Anda tidak terdaftar sebagai mitra CSR.',
            ], 403);
        }

        $validated = $request->validate([
            'program_csr_id' => 'required|exists:program_csrs,id',
            'nominal' => 'required|numeric|min:0',
            'tanggal_pendanaan' => 'nullable|date',
            'status' => 'nullable|in:Menunggu Pembayaran,Dibayar',
        ]);

        $program = ProgramCsr::findOrFail($validated['program_csr_id']);

        if ($program->sudahDihentikan()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Program ini sudah dihentikan sehingga tidak dapat didanai.',
            ], 422);
        }

        // Satu mitra CSR punya satu catatan pendanaan per program.
        $transaksi = TransaksiCsr::updateOrCreate(
            [
                'csr_id' => $csr->id,
                'program_csr_id' => $program->id,
            ],
            [
                'nominal' => $validated['nominal'],
                'tanggal_pendanaan' => $validated['tanggal_pendanaan'] ?? now()->toDateString(),
                'status' => $validated['status'] ?? 'Menunggu Pembayaran',
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Pendanaan CSR berhasil dicatat.',
            'data' => $transaksi->load(['csr', 'programCsr']),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return response()->json(\App\Models\TransaksiCsr::with(['csr.user', 'programCsr.kth.user'])->findOrFail($id));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TransaksiCsr $transaksiCsr)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TransaksiCsr $transaksiCsr)
    {
        //
    }
}

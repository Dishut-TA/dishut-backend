<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LaporanDana;
use App\Models\RincianPenggunaanDana;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LaporanDanaController extends Controller
{
    public function index()
    {
        return response()->json(LaporanDana::with('rincian')->latest()->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sumber_dana'         => 'required|string',
            'program_id'          => 'required|integer',
            'nama_program'        => 'required|string',
            'tahap'               => 'required|string',
            'tanggal_pengeluaran' => 'required|date',
            'dana_disalurkan'     => 'required|numeric',
            'rincian'             => 'required|array|min:1',
            'rincian.*.kategori'  => 'required|string',
            'rincian.*.nominal'   => 'required|numeric',
            'rincian.*.bukti'     => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        DB::beginTransaction();
        try {
            $totalRealisasi = collect($validated['rincian'])->sum('nominal');

            $laporan = LaporanDana::create([
                'sumber_dana'         => $validated['sumber_dana'],
                'program_id'          => $validated['program_id'],
                'nama_program'        => $validated['nama_program'],
                'tahap'               => $validated['tahap'],
                'tanggal_pengeluaran' => $validated['tanggal_pengeluaran'],
                'dana_disalurkan'     => $validated['dana_disalurkan'],
                'dana_direalisasikan' => $totalRealisasi,
                'status'              => 'Menunggu Verifikasi',
            ]);

            foreach ($validated['rincian'] as $index => $item) {
                $buktiPath = null;
                if ($request->hasFile("rincian.{$index}.bukti")) {
                    $buktiPath = $request->file("rincian.{$index}.bukti")->store('bukti_transaksi', 'public');
                }

                RincianPenggunaanDana::create([
                    'laporan_dana_id'      => $laporan->id,
                    'kategori_kegiatan'    => $item['kategori'],
                    'nominal'              => $item['nominal'],
                    'bukti_transaksi_path' => $buktiPath,
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Laporan dana berhasil dikirim.', 'data' => $laporan->load('rincian')], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menyimpan laporan: ' . $e->getMessage()], 500);
        }
    }

    public function show(string $id)
    {
        return response()->json(LaporanDana::with('rincian')->findOrFail($id));
    }

    public function updateStatus(Request $request, string $id)
    {
        $laporan = LaporanDana::findOrFail($id);
        
        $validated = $request->validate([
            'status' => 'required|string|in:Terverifikasi,Revisi,Menunggu Verifikasi',
            'catatan' => 'nullable|string',
        ]);

        $laporan->update($validated);

        return response()->json(['message' => 'Status laporan berhasil diperbarui.', 'data' => $laporan]);
    }
    public function update(Request $request, string $id)
    {
        $laporan = LaporanDana::findOrFail($id);

        $validated = $request->validate([
            'sumber_dana'         => 'required|string',
            'program_id'          => 'required|integer',
            'nama_program'        => 'required|string',
            'tahap'               => 'required|string',
            'tanggal_pengeluaran' => 'required|date',
            'dana_disalurkan'     => 'required|numeric',
            'rincian'             => 'required|array|min:1',
            'rincian.*.kategori'  => 'required|string',
            'rincian.*.nominal'   => 'required|numeric',
            'rincian.*.bukti'     => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        DB::beginTransaction();
        try {
            $totalRealisasi = collect($validated['rincian'])->sum('nominal');
            $laporan->update([
                'sumber_dana'         => $validated['sumber_dana'],
                'program_id'          => $validated['program_id'],
                'nama_program'        => $validated['nama_program'],
                'tahap'               => $validated['tahap'],
                'tanggal_pengeluaran' => $validated['tanggal_pengeluaran'],
                'dana_disalurkan'     => $validated['dana_disalurkan'],
                'dana_direalisasikan' => $totalRealisasi,
                'status'              => 'Menunggu Verifikasi',
                'catatan'             => null,
            ]);

            $laporan->rincian()->delete();

            foreach ($validated['rincian'] as $index => $item) {
                $buktiPath = null;
                if ($request->hasFile("rincian.{$index}.bukti")) {
                    $buktiPath = $request->file("rincian.{$index}.bukti")->store('bukti_transaksi', 'public');
                }

                RincianPenggunaanDana::create([
                    'laporan_dana_id'      => $laporan->id,
                    'kategori_kegiatan'    => $item['kategori'],
                    'nominal'              => $item['nominal'],
                    'bukti_transaksi_path' => $buktiPath,
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Revisi laporan berhasil dikirim.', 'data' => $laporan->load('rincian')], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal mengupdate laporan: ' . $e->getMessage()], 500);
        } 
    }
}
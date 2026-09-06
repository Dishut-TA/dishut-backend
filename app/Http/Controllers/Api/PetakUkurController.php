<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PetakUkur;
use Illuminate\Http\JsonResponse;

class PetakUkurController extends Controller
{
    /**
     * Mengambil daftar PU untuk suatu penugasan
     */
    public function index($penugasan_id): JsonResponse
    {
        $pu = PetakUkur::with('dataTanamans.seed')->where('penugasan_id', $penugasan_id)->get();
        return response()->json([
            'message' => 'Daftar Petak Ukur',
            'data' => $pu
        ]);
    }

    /**
     * Menyimpan data Petak Ukur baru
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'penugasan_id' => 'required|exists:penugasans,id',
            'nama' => 'required|string',
            'luas' => 'required|numeric',
            'polygon_data' => 'required|array',
        ]);

        $pu = PetakUkur::updateOrCreate(
            [
                'penugasan_id' => $request->penugasan_id,
                'nama' => $request->nama,
            ],
            [
                'luas' => $request->luas,
                'polygon_data' => $request->polygon_data,
                'status' => 'Selesai',
            ]
        );

        return response()->json([
            'message' => 'Petak Ukur berhasil disimpan',
            'data' => $pu
        ], 200);
    }

    public function getTanaman($id): JsonResponse
    {
        $tanaman = \App\Models\DataTanaman::with('seed.specifications')->where('petak_ukur_id', $id)->get();
        return response()->json([
            'message' => 'Daftar Tanaman PU',
            'data' => $tanaman
        ]);
    }

    public function storeTanaman(Request $request, $id): JsonResponse
    {
        $request->validate([
            'seed_id' => 'nullable|exists:seeds,id',
            'nama_tanaman' => 'nullable|string',
            'jumlah' => 'required|integer|min:1',
            'kondisi_tanaman' => 'nullable|string',
            'keterangan' => 'nullable|string',
            'tinggi_tanaman' => 'nullable|numeric',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'photo' => 'nullable|image|max:5120'
        ]);

        $fotoUrl = null;
        if ($request->hasFile('photo')) {
            $fotoUrl = $request->file('photo')->store('data_tanaman', 'public');
        }

        $tanaman = \App\Models\DataTanaman::create([
            'petak_ukur_id' => $id,
            'seed_id' => $request->seed_id,
            'nama_tanaman' => $request->nama_tanaman,
            'jumlah' => $request->jumlah,
            'kondisi_tanaman' => $request->kondisi_tanaman,
            'keterangan' => $request->keterangan,
            'tinggi_tanaman' => $request->tinggi_tanaman,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'foto_url' => $fotoUrl
        ]);

        return response()->json([
            'message' => 'Data tanaman berhasil disimpan',
            'data' => $tanaman
        ], 201);
    }

    public function updateTanaman(Request $request, $id): JsonResponse
    {
        $request->validate([
            'kondisi_tanaman' => 'required|string',
            'keterangan' => 'nullable|string',
            'foto_url' => 'nullable|string'
        ]);

        $tanaman = \App\Models\DataTanaman::findOrFail($id);
        
        $tanaman->update([
            'kondisi_tanaman' => $request->kondisi_tanaman,
            'keterangan' => $request->keterangan,
            'foto_url' => $request->foto_url
        ]);

        return response()->json([
            'message' => 'Data tanaman berhasil diperbarui',
            'data' => $tanaman
        ], 200);
    }
}

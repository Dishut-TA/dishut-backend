<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Kth;
use App\Http\Resources\KthResource;
use App\Imports\KthImport;
use Maatwebsite\Excel\Facades\Excel; 
use Illuminate\Support\Facades\DB;

class KthController extends Controller
{
    public function index()
    {
        return KthResource::collection(Kth::latest()->get());
    }

    public function store(Request $request)
    {
        // Validasi input manual
        $validated = $request->validate([
            'cdk' => 'required|string|max:255',
            'kabupaten_kota' => 'required|string|max:255',
            'kecamatan' => 'required|string|max:255',
            'desa_kelurahan' => 'required|string|max:255',
            'nama' => 'required|string|max:255',
            'ketua' => 'required|string|max:255',
            'jenis_usaha' => 'required|string|max:255',
        ]);

        $item = Kth::create($validated);
        return new KthResource($item);
    }

    public function show(string $id)
    {
        $item = Kth::findOrFail($id);
        return new KthResource($item);
    }

    public function getById(string $id)
    {
        return $this->show($id);
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'cdk' => 'sometimes|required|string|max:255',
            'kabupaten_kota' => 'sometimes|required|string|max:255',
            'kecamatan' => 'sometimes|required|string|max:255',
            'desa_kelurahan' => 'sometimes|required|string|max:255',
            'nama' => 'sometimes|required|string|max:255',
            'ketua' => 'sometimes|required|string|max:255',
            'jenis_usaha' => 'sometimes|required|string|max:255',
        ]);

        $item = Kth::findOrFail($id);
        $item->update($validated);
        
        return new KthResource($item);
    }

    public function destroy(string $id)
    {
        $item = Kth::findOrFail($id);
        $item->delete();
        
        return response()->json(['message' => 'Data KTH berhasil dihapus']);
    }

    // --- METHOD BARU UNTUK UPLOAD EXCEL ---
    public function importExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:2048'
        ]);

        try {
            DB::beginTransaction();
            
            // Proses import data menggunakan class KthImport
            Excel::import(new KthImport, $request->file('file'));
            
            DB::commit();

            return response()->json([
                'message' => 'Data KTH dari Excel berhasil diimpor secara otomatis!'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal mengimpor data. Pastikan format template Excel benar.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Kth;
use App\Http\Resources\KthResource;
use App\Imports\KthImport;
use Maatwebsite\Excel\Facades\Excel; 
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            'cdk'                   => 'required|string|max:255',
            'kabupaten_kota'        => 'required|string|max:255',
            'kecamatan'             => 'required|string|max:255',
            'desa_kelurahan'        => 'required|string|max:255',
            'nama'                  => 'required|string|max:255',
            'ketua'                 => 'required|string|max:255',
            'jenis_usaha'           => 'required|string|max:255',
            'nomor_sk'              => 'nullable|string|max:255',
            'no_wa_ketua'           => 'nullable|string|max:30',
            'jumlah_anggota'        => 'nullable|string|max:50',
            'kelas_kth'             => 'nullable|string|max:100',
            'alamat_sekretariat'    => 'nullable|string',
            'nama_bank'             => 'nullable|string|max:100',
            'no_rekening'           => 'nullable|string|max:50',
            'nama_pemilik_rekening' => 'nullable|string|max:255',
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
            'cdk'                   => 'sometimes|required|string|max:255',
            'kabupaten_kota'        => 'sometimes|required|string|max:255',
            'kecamatan'             => 'sometimes|required|string|max:255',
            'desa_kelurahan'        => 'sometimes|required|string|max:255',
            'nama'                  => 'sometimes|required|string|max:255',
            'ketua'                 => 'sometimes|required|string|max:255',
            'jenis_usaha'           => 'sometimes|required|string|max:255',
            'nomor_sk'              => 'nullable|string|max:255',
            'no_wa_ketua'           => 'nullable|string|max:30',
            'jumlah_anggota'        => 'nullable|string|max:50',
            'kelas_kth'             => 'nullable|string|max:100',
            'alamat_sekretariat'    => 'nullable|string',
            'nama_bank'             => 'nullable|string|max:100',
            'no_rekening'           => 'nullable|string|max:50',
            'nama_pemilik_rekening' => 'nullable|string|max:255',
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

    // --- METHOD UNTUK UPLOAD EXCEL / CSV KTH ---
    public function importExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:20480|mimes:xlsx,xls,csv,txt'
        ], [
            'file.required' => 'Pilih file Excel/CSV terlebih dahulu!',
            'file.mimes'    => 'Format file harus berupa Excel (.xlsx, .xls) atau CSV (.csv)!',
            'file.max'      => 'Ukuran file maksimal 20MB!'
        ]);

        $file = $request->file('file');

        try {
            DB::beginTransaction();
            
            // Deteksi otomatis delimiter jika file adalah CSV
            $delimiter = ',';
            $ext = strtolower($file->getClientOriginalExtension());
            if ($ext === 'csv' || str_contains($file->getMimeType(), 'csv')) {
                $handle = fopen($file->getRealPath(), 'r');
                if ($handle) {
                    $firstLine = fgets($handle);
                    fclose($handle);
                    if ($firstLine !== false && substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
                        $delimiter = ';';
                    }
                }
            }

            $import = new KthImport($delimiter);

            if ($ext === 'csv') {
                Excel::import($import, $file, null, \Maatwebsite\Excel\Excel::CSV);
            } else {
                Excel::import($import, $file);
            }
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Data KTH berhasil diimpor! Total {$import->importedCount} data kelompok berhasil ditambahkan.",
                'total_imported' => $import->importedCount
            ], 200);

        } catch (ValidationException $e) {
            DB::rollBack();
            $errors = $e->errors();
            $firstMessage = collect($errors)->flatten()->first() ?? $e->getMessage();

            return response()->json([
                'success' => false,
                'message' => $firstMessage,
                'errors'  => $errors
            ], 422);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengimpor data: ' . $e->getMessage(),
                'error'   => $e->getMessage()
            ], 422);
        }
    }
}
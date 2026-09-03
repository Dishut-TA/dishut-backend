<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Zonasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class ZonasiController extends Controller
{
    public function index()
    {
        $data = Zonasi::latest()->get();
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file_zonasi' => 'required|file|mimes:zip|max:51200',
        ]);

        try {
            $file = $request->file('file_zonasi');
            $filename = 'zonasi_' . time() . '_' . Str::random(5) . '.zip';

            $path = $file->storeAs('zonasi_uploads', $filename, 'local');
            $absolutePath = Storage::disk('local')->path($path);

            $pythonUrl = rtrim(config('cpi.engine_url', 'http://127.0.0.1:8001'), '/') . '/extract-zonasi';

            $response = Http::timeout(60)
                ->attach('file', file_get_contents($absolutePath), $filename)
                ->post($pythonUrl);

            if ($response->failed()) {
                throw new Exception('Gagal mengekstrak data di Python Engine.');
            }

            $extractedData = $response->json('data');

            $insertedData = [];
            foreach ($extractedData as $row) {
                $insertedData[] = Zonasi::create([
                    'kabupaten'   => $row['kabupaten'],
                    'kecamatan'   => $row['kecamatan'],
                    'desa'        => $row['desa'],
                    'file_path'   => $path
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data Zonasi berhasil diekstrak dan disimpan.',
                'data'    => $insertedData
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
}

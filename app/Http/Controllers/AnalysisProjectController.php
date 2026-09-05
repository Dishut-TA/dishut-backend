<?php

namespace App\Http\Controllers;

use App\Http\Resources\AnalysisProjectResource;
use App\Http\Resources\AnalysisResultResource;
use App\Models\AnalysisProject;
use App\Models\AnalysisResult;
use App\Models\AnalysisResultZone;
use App\Services\CpiEngineService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AnalysisProjectController extends Controller
{
    public function __construct(private CpiEngineService $cpiService)
    {
    }

    // =========================================================================
    // POST /api/projects/upload
    // =========================================================================

    /**
     * Upload file geospasial, simpan ke storage, lalu panggil Python CPI Engine.
     *
     * Alur sesuai PRD Section 3:
     * 1. Validasi file (ekstensi, ukuran)
     * 2. Simpan file ke storage server
     * 3. Simpan path ke database
     * 4. Kirim path ke Python via HTTP POST
     * 5. Simpan response Python ke database
     * 6. Return hasil ke FE
     */
    public function upload(Request $request): JsonResponse
    {
        // 1. Validasi input
        // Catatan: field 'kemiringan' dari form FE diterima tapi TIDAK diteruskan ke Python.
        // Python CPI Engine menghitung kemiringan (slope) secara otomatis dari file DEM.
        // Field ini ada di form FE (mockup Figma) dan diterima agar tidak error validasi.
        $request->validate([
            'nama_project'      => 'nullable|string|max:255',
            'dem'               => 'required|file|mimes:tif,tiff|max:1048576',       // max 1GB
            'tutupan_lahan'     => 'required|file|mimes:tif,tiff,zip,geojson,json|max:1048576',
            'curah_hujan'       => 'required|file|mimes:tif,tiff|max:1048576',
            'jenis_tanah'       => 'required|file|mimes:tif,tiff,zip,geojson,json|max:1048576',
            'das'               => 'required|file|mimes:zip,geojson,json|max:1048576',
            'kemiringan'        => 'nullable|file|mimes:tif,tiff|max:1048576',        // Diterima tapi diabaikan — slope auto dari DEM
            'batas_wilayah'     => 'nullable|file|mimes:zip,geojson,json|max:1048576',
            'target_resolution' => 'nullable|numeric|min:100|max:10000',
            'save_intermediate' => 'nullable|boolean',
            'ahp_matrix'        => 'nullable|json',
        ]);

        // 2. Buat project code unik
        $projectCode = 'project_' . date('Ymd') . '_' . Str::random(8);
        $baseDir = 'projects/' . $projectCode;

        // 3. Simpan semua file ke storage
        try {
            $demPath        = $this->storeFile($request->file('dem'), $baseDir, 'dem');
            $landcoverPath  = $this->storeFile($request->file('tutupan_lahan'), $baseDir, 'landcover');
            $rainfallPath   = $this->storeFile($request->file('curah_hujan'), $baseDir, 'rainfall');
            $soilPath       = $this->storeFile($request->file('jenis_tanah'), $baseDir, 'soil');
            $dasPath        = $this->storeFile($request->file('das'), $baseDir, 'das');
            $adminPath      = $request->hasFile('batas_wilayah')
                ? $this->storeFile($request->file('batas_wilayah'), $baseDir, 'admin')
                : null;
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Gagal menyimpan file: ' . $e->getMessage(),
            ], 500);
        }

        // 4. Simpan record project ke database
        $project = AnalysisProject::create([
            'project_code'   => $projectCode,
            'user_id'        => $request->user()?->id,
            'project_name'   => $request->input('nama_project', $projectCode),
            'status'         => AnalysisProject::STATUS_UPLOADED,
            'dem_path'       => $demPath,
            'landcover_path' => $landcoverPath,
            'rainfall_path'  => $rainfallPath,
            'soil_path'      => $soilPath,
            'das_path'       => $dasPath,
            'admin_path'     => $adminPath,
        ]);

        // 5. Update status ke processing
        $project->update(['status' => AnalysisProject::STATUS_PROCESSING]);

        // 6. Panggil Python CPI Engine
        try {
            $ahpMatrix        = $request->input('ahp_matrix')
                ? json_decode($request->input('ahp_matrix'), true)
                : null;
            $targetResolution = $request->input('target_resolution')
                ? (float) $request->input('target_resolution')
                : 5000; // Default 5000 untuk testing
            $saveIntermediate = filter_var($request->input('save_intermediate', false), FILTER_VALIDATE_BOOLEAN);

            $zoneApiUrl     = null; // Bypass untuk menghindari deadlock HTTP
            $historyApiUrl  = null;

// Generate GeoJSON villages secara lokal dari Database Laravel untuk dijadikan batas administrasi
$villages = \App\Models\Village::with(['district.city'])->whereNotNull('geometry')->get();

$adminGeoJsonPath = null;

if ($villages->isNotEmpty()) {
    $features = $villages->map(function ($village) {
        $geometry = is_string($village->geometry) ? json_decode($village->geometry, true) : $village->geometry;
        return [
            'type' => 'Feature',
            'properties' => [
                'id'        => $village->id,
                'desa'      => $village->name,
                'kecamatan' => $village->district?->name,
                'kabupaten' => $village->district?->city?->name,
                'provinsi'  => 'Jawa Barat',
            ],
            'geometry' => $geometry,
        ];
    })->filter(fn($f) => !empty($f['geometry']))->values()->toArray();

    $adminGeoJsonPath = storage_path("app/private/projects/{$project->project_code}/zones_master_local.geojson");
    if (!file_exists(dirname($adminGeoJsonPath))) {
        mkdir(dirname($adminGeoJsonPath), 0755, true);
    }

    // SIMPAN GeoJSON agar dibaca oleh Python CPI Engine
    file_put_contents($adminGeoJsonPath, json_encode([
        'type' => 'FeatureCollection', 
        'features' => $features
    ]));
}

// Jika user meng-upload file 'batas_wilayah', utamakan file upload user. Jika tidak ada, pakai $adminGeoJsonPath dari DB.
$finalAdminPath = $adminPath ?: $adminGeoJsonPath;

// Panggil Service Python
$pythonResponse = $this->cpiService->analyze(
    projectId:        $project->project_code,
    demPath:          $demPath,
    landcoverPath:    $landcoverPath,
    rainfallPath:     $rainfallPath,
    soilPath:         $soilPath,
    dasPath:          $dasPath,
    adminPath:        $finalAdminPath, // <-- PASANG PATH ADMIN DI SINI
    targetResolution: $targetResolution,
    saveIntermediate: $saveIntermediate,
    ahpMatrix:        $ahpMatrix,
    zoneApiUrl:       $zoneApiUrl,
    historyApiUrl:    $historyApiUrl,
);
            // 7. Simpan response ke tabel analysis_results
            $mapData   = $pythonResponse['map'] ?? [];
            $filesData = $pythonResponse['files'] ?? [];

            $result = AnalysisResult::create([
                'project_id'           => $project->id,
                'job_id'               => $pythonResponse['job_id'],
                'status'               => $pythonResponse['status'] ?? 'completed',
                'result_json'          => $pythonResponse,
                'table_json'           => $pythonResponse['table'] ?? [],
                'metadata_json'        => $pythonResponse['ahp'] ?? [],
                'critical_geojson_url' => $mapData['critical_geojson'] ?? null,
                'map_html_url'         => $mapData['html_preview'] ?? null,
                'cpi_raster_url'       => $mapData['cpi_raster'] ?? null,
                'class_raster_url'     => $mapData['class_raster'] ?? null,
            ]);

            // 8. Simpan breakdown zona (opsional) jika ada data tabel
            $this->saveZoneBreakdown($result, $pythonResponse['table'] ?? []);

            // 9. Update project: completed
            $project->update([
                'status'         => AnalysisProject::STATUS_COMPLETED,
                'python_job_id'  => $pythonResponse['job_id'],
            ]);

            return response()->json([
                'message'         => 'Analisis berhasil diselesaikan.',
                'data'            => new AnalysisProjectResource($project->load('result')),
                'kemiringan_note' => 'Data kemiringan dihitung otomatis dari file DEM oleh Python CPI Engine.',
            ], 201);

        } catch (Exception $e) {
            // Simpan error dan update status
            $project->update([
                'status'        => AnalysisProject::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            Log::error('[AnalysisProjectController] Analisis gagal', [
                'project_code' => $projectCode,
                'error'        => $e->getMessage(),
            ]);

            return response()->json([
                'message'      => 'Analisis gagal diproses.',
                'error'        => $e->getMessage(),
                'project_id'   => $project->id,
                'project_code' => $projectCode,
            ], 500);
        }
    }

    // =========================================================================
    // GET /api/projects
    // =========================================================================

    /**
     * Daftar semua project analisis.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AnalysisProject::with(['result.zones'])
            ->where('status', AnalysisProject::STATUS_COMPLETED)
            ->latest();

        if ($request->user()) {
            $query->where('user_id', $request->user()->id);
        }

        $projects = $query->paginate($request->get('per_page', 15));

        return response()->json(AnalysisProjectResource::collection($projects)->response()->getData(true));
    }

    // =========================================================================
    // GET /api/projects/{id}
    // =========================================================================

    /**
     * Detail satu project analisis.
     */
    public function show(int $id): JsonResponse
    {
        $project = AnalysisProject::with('result')->findOrFail($id);

        return response()->json([
            'data' => new AnalysisProjectResource($project),
        ]);
    }

    // =========================================================================
    // GET /api/projects/{id}/result
    // =========================================================================

    /**
     * Kembalikan JSON hasil analisis lengkap dari Python.
     */
    public function result(int $id): JsonResponse
    {
        $project = AnalysisProject::findOrFail($id);
        $result  = $project->result;

        if (!$result) {
            return response()->json(['message' => 'Hasil analisis belum tersedia.'], 404);
        }

        return response()->json([
            'data' => new AnalysisResultResource($result),
        ]);
    }

    // =========================================================================
    // GET /api/projects/{id}/table
    // =========================================================================

    /**
     * Kembalikan data tabel zonal statistics untuk dashboard.
     */
    public function table(int $id): JsonResponse
    {
        $project = \App\Models\AnalysisProject::findOrFail($id);
        $result  = $project->result;

        if (!$result) {
            return response()->json(['message' => 'Data tabel belum tersedia.'], 404);
        }

        // Ambil data yang SUDAH dimodifikasi dengan KTH di database beserta relasi desa
        $zones = \App\Models\AnalysisResultZone::with('village')->where('result_id', $result->id)->get();

        $mappedData = $zones->map(function ($z) {
            return [
                'zone_id'                => $z->zone_id,
                'kota_kabupaten'         => $z->kabupaten,
                'kecamatan'              => $z->kecamatan,
                'desa_kelurahan'         => $z->desa,
                'status_lahan_kritis'    => $z->status_lahan_kritis,
                'skor_cpi_rata2'         => $z->skor_cpi,
                'rekomendasi_intervensi' => $z->rekomendasi_intervensi,
                'cdk'                    => $z->cdk,             // Field KTH
                'nama_kelompok'          => $z->nama_kelompok,   // Field KTH
                'ketua_kelompok'         => $z->ketua_kelompok,  // Field KTH
                'latitude'               => $z->village ? $z->village->latitude : null,
                'longitude'              => $z->village ? $z->village->longitude : null,
            ];
        });

        return response()->json(['data' => $mappedData]);
    }

    // =========================================================================
    // GET /api/projects/{id}/map
    // =========================================================================

    /**
     * Kembalikan GeoJSON peta kekritisan lahan untuk rendering di Frontend.
     * FE disarankan menggunakan GeoJSON zonal (batas wilayah) agar pewarnaan
     * mengikuti batas administrasi, bukan raster polygon individual.
     */
    public function map(int $id): JsonResponse
    {
        $project = AnalysisProject::findOrFail($id);
        $result  = $project->result;

        if (!$result || !$result->critical_geojson_url) {
            return response()->json(['message' => 'Data peta belum tersedia.'], 404);
        }

        $geojsonUrl = $result->critical_geojson_url;
        $geoJsonData = null;

        if (str_starts_with($geojsonUrl, '/result/')) {
            $pythonUrl = rtrim(config('cpi.engine_url', 'http://127.0.0.1:8001'), '/') . $geojsonUrl;
            try {
                $response = Http::timeout(30)->get($pythonUrl);
                if ($response->successful()) {
                    $geoJsonData = $response->json();
                }
            } catch (Exception $e) {
                Log::warning('[AnalysisProjectController] Gagal proxy GeoJSON dari Python');
            }
        }

        if (!$geoJsonData) {
            return response()->json([
                'geojson_url' => $geojsonUrl,
                'message'     => 'Akses GeoJSON melalui URL yang disediakan.',
            ]);
        }

        $zonesFromDb = AnalysisResultZone::where('result_id', $result->id)->get()->keyBy('zone_id');

        if (isset($geoJsonData['features']) && is_array($geoJsonData['features'])) {
            foreach ($geoJsonData['features'] as &$feature) {
                $zoneId = $feature['properties']['zone_id'] ?? null;
                
                if ($zoneId && $zonesFromDb->has($zoneId)) {
                    $dbZone = $zonesFromDb->get($zoneId);
                    $feature['properties']['cdk'] = $dbZone->cdk;
                    $feature['properties']['nama_kelompok'] = $dbZone->nama_kelompok;
                    $feature['properties']['ketua_kelompok'] = $dbZone->ketua_kelompok;
                }
            }
        }

        return response()->json($geoJsonData);
    }

    // =========================================================================
    // GET /api/projects/{id}/download/{filename}
    // =========================================================================

    /**
     * Download file output analisis (GeoTIFF, GeoJSON, CSV, HTML).
     * PHP mem-proxy request ke Python service untuk mendapatkan file.
     */
    public function download(int $id, string $filename): BinaryFileResponse|JsonResponse
    {
        $project = AnalysisProject::findOrFail($id);
        $result  = $project->result;

        if (!$result) {
            return response()->json(['message' => 'Hasil analisis belum tersedia.'], 404);
        }

        $jobId    = $result->job_id;
        $safeFile = basename($filename); // Sanitasi nama file (hindari path traversal)

        // Proxy download dari Python service
        $pythonUrl = rtrim(config('cpi.engine_url', 'http://127.0.0.1:8001'), '/') .
            "/result/{$jobId}/file/{$safeFile}";

        try {
            $response = Http::timeout(120)
                ->sink($tmpPath = tempnam(sys_get_temp_dir(), 'cpi_'))
                ->get($pythonUrl);

            if ($response->failed()) {
                return response()->json(['message' => 'File tidak ditemukan di Python service.'], 404);
            }

            return response()->download($tmpPath, $safeFile)->deleteFileAfterSend(true);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Gagal mengunduh file: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // GET /api/zones
    // =========================================================================

    /**
     * Endpoint master zonasi untuk dikonsumsi Python CPI Engine.
     * Python memanggil URL ini via zone_api_url parameter.
     * Mengembalikan data wilayah (villages + geometri) dalam format GeoJSON.
     */
    public function zones(): JsonResponse
    {
        // Ambil data desa/kelurahan yang punya geometry
        $villages = \App\Models\Village::with(['district.city'])
            ->whereNotNull('geometry')
            ->get();

        if ($villages->isEmpty()) {
            // Fallback: kembalikan array kosong yang tetap valid untuk Python
            return response()->json([
                'type'     => 'FeatureCollection',
                'features' => [],
            ]);
        }

        $features = $villages->map(function ($village) {
            $geometry = is_string($village->geometry)
                ? json_decode($village->geometry, true)
                : $village->geometry;

            return [
                'type'       => 'Feature',
                'properties' => [
                    'id'        => $village->id,
                    'desa'      => $village->name,
                    'kecamatan' => $village->district?->name,
                    'kabupaten' => $village->district?->city?->name,
                    'provinsi'  => null, // Tambahkan jika ada field provinsi
                ],
                'geometry' => $geometry,
            ];
        })->filter(fn($f) => !empty($f['geometry']))->values();

        return response()->json([
            'type'     => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    // =========================================================================
    // GET /api/projects/{id}/zones
    // =========================================================================

    /**
     * Endpoint untuk mendapatkan daftar wilayah (tabel zona) beserta relasi field validation.
     * Digunakan oleh Kabid untuk me-render tabel detail.
     */
    public function zonesList(int $id): JsonResponse
    {
        $project = AnalysisProject::findOrFail($id);
        $result = $project->result;

        if (!$result) {
            return response()->json(['message' => 'Hasil analisis belum tersedia.'], 404);
        }

        $zones = AnalysisResultZone::with(['fieldValidations', 'village'])
            ->where('result_id', $result->id)
            ->get();

        return response()->json([
            'data' => $zones,
        ]);
    }

    // =========================================================================
    // HELPERS (private)
    // =========================================================================

    /**
     * Simpan file upload ke storage dan kembalikan path absolutnya.
     * Python membutuhkan path absolut agar bisa membaca file langsung.
     */
    private function storeFile($file, string $directory, string $prefix): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $filename  = $prefix . '_' . time() . '_' . Str::random(6) . '.' . $extension;

        // Simpan ke storage/app/projects/{projectCode}/{filename}
        $relativePath = $file->storeAs($directory, $filename, 'local');

        // Kembalikan path absolut yang bisa dibaca Python
        return Storage::disk('local')->path($relativePath);
    }

    /**
     * Simpan breakdown per zona ke tabel analysis_result_zones.
     * Hanya dipanggil jika ada data tabel dari Python.
     */
    private function saveZoneBreakdown(AnalysisResult $result, array $tableRows): void
    {
        if (empty($tableRows)) {
            return;
        }

        // Ambil semua data KTH sekali saja agar tidak berat ke database (menghindari N+1 Query)
        $semuaKth = \App\Models\Kth::all();

        $zones = array_map(function ($row) use ($result, $semuaKth) {
            $kabupaten = $row['kota_kabupaten'] ?? $row['kabupaten'] ?? null;
            $kecamatan = $row['kecamatan'] ?? null;
            $desa = $row['desa_kelurahan'] ?? $row['desa'] ?? null;

            // 1. Tentukan CDK Otomatis
            $cdk = $kabupaten ? $this->determineCdk($kabupaten) : null;

            // 2. Cari KTH Otomatis (Prioritas: Cocok Desa, kalau tidak ada cari yg sedistrik/kecamatan)
            $kthTerpilih = null;
            if ($desa && $kecamatan) {
                $kthTerpilih = $semuaKth->first(function ($k) use ($desa, $kecamatan) {
                    return strtolower($k->desa_kelurahan) === strtolower($desa) && 
                           strtolower($k->kecamatan) === strtolower($kecamatan);
                });

                if (!$kthTerpilih) {
                    $kthTerpilih = $semuaKth->first(function ($k) use ($kecamatan) {
                        return strtolower($k->kecamatan) === strtolower($kecamatan);
                    });
                }
            }

            return [
                'result_id'              => $result->id,
                'zone_id'                => $row['zone_id'] ?? null,
                'provinsi'               => $row['provinsi'] ?? null,
                'kabupaten'              => $kabupaten,
                'kecamatan'              => $kecamatan,
                'desa'                   => $desa,
                'skor_cpi'               => $row['skor_cpi_rata2'] ?? $row['skor_cpi'] ?? null,
                'status_lahan_kritis'    => $row['status_lahan_kritis'] ?? null,
                'luas_ha'                => $row['luas_ha'] ?? null,
                'score_landcover'        => $row['score_landcover_rata2'] ?? null,
                'score_rainfall'         => $row['score_rainfall_rata2'] ?? null,
                'score_soil'             => $row['score_soil_rata2'] ?? null,
                'score_slope'            => $row['score_slope_rata2'] ?? null,
                'slope_percent'          => $row['slope_percent_rata2'] ?? null,
                'alasan_skor'            => $row['alasan_skor'] ?? null,
                'riwayat_intervensi'     => $row['riwayat_intervensi'] ?? null,
                'rekomendasi_intervensi' => $row['rekomendasi_intervensi'] ?? null,
                'cdk'                    => $cdk,
                'nama_kelompok'          => $kthTerpilih ? $kthTerpilih->nama : null,
                'ketua_kelompok'         => $kthTerpilih ? $kthTerpilih->ketua : null,
                'status_validasi_penyuluh' => 'Belum',
                'status_kelayakan'       => 'Belum Diverifikasi',
                'created_at'             => now(),
                'updated_at'             => now(),
            ];
        }, $tableRows);

        foreach (array_chunk($zones, 100) as $chunk) {
            AnalysisResultZone::insert($chunk);
        }
    }

    private function determineCdk(string $kabupaten): string
    {
        $kabupaten = strtolower($kabupaten);

        if (Str::contains($kabupaten, ['bogor', 'depok', 'bekasi'])) return 'CDK WILAYAH I';
        if (Str::contains($kabupaten, ['purwakarta', 'karawang', 'subang'])) return 'CDK WILAYAH II';
        if (Str::contains($kabupaten, ['sukabumi'])) return 'CDK WILAYAH III';
        if (Str::contains($kabupaten, ['cianjur', 'bandung barat'])) return 'CDK WILAYAH IV';
        if (Str::contains($kabupaten, ['garut', 'bandung']) && !Str::contains($kabupaten, 'barat')) return 'CDK WILAYAH V';
        
        return '-';
    }
}

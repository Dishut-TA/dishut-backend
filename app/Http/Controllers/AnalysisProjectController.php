<?php

namespace App\Http\Controllers;

use App\Http\Resources\AnalysisProjectResource;
use App\Http\Resources\AnalysisResultResource;
use App\Models\AnalysisProject;
use App\Models\AnalysisResult;
use App\Models\AnalysisResultZone;
use App\Models\Kth;
use App\Models\Village;
use App\Services\CpiEngineService;
use App\Services\PlantRecommendationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AnalysisProjectController extends Controller
{
    private const MAX_UPLOAD_KB = 5120; // 5 MB per file

    public function __construct(
        private CpiEngineService $cpiService,
        private PlantRecommendationService $plantRecommendationService,
    ) {
    }

    // =========================================================================
    // POST /api/projects/upload
    // =========================================================================

    public function upload(Request $request): JsonResponse
    {
        $request->validate($this->analysisValidationRules(false));

        $projectCode = 'project_' . date('Ymd') . '_' . Str::random(8);
        $baseDir = 'projects/' . $projectCode;

        try {
            $paths = [
                'dem_path' => $this->storeFile($request->file('dem'), $baseDir, 'dem'),
                'landcover_path' => $this->storeFile($request->file('tutupan_lahan'), $baseDir, 'landcover'),
                'rainfall_path' => $this->storeFile($request->file('curah_hujan'), $baseDir, 'rainfall'),
                'soil_path' => $this->storeFile($request->file('jenis_tanah'), $baseDir, 'soil'),
                'das_path' => $this->storeFile($request->file('das'), $baseDir, 'das'),
                'admin_path' => $request->hasFile('batas_wilayah')
                    ? $this->storeFile($request->file('batas_wilayah'), $baseDir, 'admin')
                    : null,
            ];
        } catch (Exception $e) {
            Storage::disk('local')->deleteDirectory($baseDir);
            return response()->json(['message' => 'Gagal menyimpan file: ' . $e->getMessage()], 500);
        }

        $project = AnalysisProject::create(array_merge($paths, [
            'project_code' => $projectCode,
            'user_id' => $request->user()?->id,
            'project_name' => $request->input('nama_project', $projectCode),
            'status' => AnalysisProject::STATUS_PROCESSING,
        ]));

        try {
            $pythonResponse = $this->runCpi($request, $project, $paths);
            $this->persistAnalysisRevision($project, $pythonResponse, array_merge($paths, [
                'project_name' => $request->input('nama_project', $project->project_name),
            ]));

            return response()->json([
                'message' => 'Analisis berhasil diselesaikan.',
                'data' => new AnalysisProjectResource($project->fresh()->load('result')),
                'kemiringan_note' => 'Data kemiringan dihitung otomatis dari file DEM oleh Python CPI Engine.',
            ], 201);
        } catch (Exception $e) {
            return $this->handleInitialAnalysisFailure($e, $project, $baseDir);
        }
    }

    // =========================================================================
    // POST /api/projects/{id}/reanalyze
    // =========================================================================

    /**
     * Edit data peta/indikator sebuah project lalu analisis ulang.
     *
     * File yang tidak dikirim akan menggunakan file versi sebelumnya. Hasil lama
     * tidak ditimpa/dihapus: AnalysisProject::result() otomatis menunjuk revisi
     * AnalysisResult terbaru. Ini menjaga referensi downstream ke zona lama.
     */
    public function reanalyze(Request $request, int $id): JsonResponse
    {
        $project = AnalysisProject::with('result')->findOrFail($id);
        $request->validate($this->analysisValidationRules(true));

        $baseDir = 'projects/' . $project->project_code;
        $oldPaths = $this->projectPaths($project);
        $replacementPaths = [];

        $fieldMap = [
            'dem' => ['column' => 'dem_path', 'prefix' => 'dem'],
            'tutupan_lahan' => ['column' => 'landcover_path', 'prefix' => 'landcover'],
            'curah_hujan' => ['column' => 'rainfall_path', 'prefix' => 'rainfall'],
            'jenis_tanah' => ['column' => 'soil_path', 'prefix' => 'soil'],
            'das' => ['column' => 'das_path', 'prefix' => 'das'],
            'batas_wilayah' => ['column' => 'admin_path', 'prefix' => 'admin'],
        ];

        try {
            foreach ($fieldMap as $requestField => $meta) {
                if ($request->hasFile($requestField)) {
                    $replacementPaths[$meta['column']] = $this->storeFile(
                        $request->file($requestField),
                        $baseDir,
                        $meta['prefix']
                    );
                }
            }

            $candidatePaths = array_merge($oldPaths, $replacementPaths);
            foreach (['dem_path', 'landcover_path', 'rainfall_path', 'soil_path', 'das_path'] as $requiredPath) {
                if (empty($candidatePaths[$requiredPath]) || !is_file($candidatePaths[$requiredPath])) {
                    throw new Exception("File indikator wajib {$requiredPath} tidak tersedia. Silakan upload ulang file tersebut.", 422);
                }
            }

            $pythonResponse = $this->runCpi($request, $project, $candidatePaths);
            $updates = array_merge($candidatePaths, [
                'project_name' => $request->input('nama_project', $project->project_name),
            ]);
            $this->persistAnalysisRevision($project, $pythonResponse, $updates);

            // File indikator versi lama sengaja dipertahankan di storage.
            // AnalysisResult lama tetap dapat diaudit/direproduksi walaupun project
            // sekarang menunjuk ke file indikator revisi terbaru.

            return response()->json([
                'message' => 'Data peta berhasil diperbarui dan analisis ulang selesai.',
                'data' => new AnalysisProjectResource($project->fresh()->load('result')),
                'replaced_indicators' => array_keys($replacementPaths),
            ]);
        } catch (Exception $e) {
            // File baru yang gagal dianalisis dibuang; file/result lama tetap aman.
            foreach ($replacementPaths as $newPath) {
                $this->deleteLocalAbsoluteFile($newPath);
            }

            Log::warning('[AnalysisProjectController] Re-analysis gagal; revisi lama dipertahankan', [
                'project_code' => $project->project_code,
                'error' => $e->getMessage(),
            ]);

            $status = $this->safeHttpStatus($e);
            return response()->json([
                'message' => $status === 422
                    ? 'Validasi koordinat/lokasi data GIS gagal. Analisis ulang dibatalkan dan data lama tetap dipertahankan.'
                    : 'Analisis ulang gagal. Data dan hasil analisis sebelumnya tetap dipertahankan.',
                'error' => $e->getMessage(),
                'project_id' => $project->id,
            ], $status);
        }
    }

    // =========================================================================
    // GET /api/projects
    // =========================================================================

    public function index(Request $request): JsonResponse
    {
        $query = AnalysisProject::with(['result.zones'])
            ->where('status', AnalysisProject::STATUS_COMPLETED)
            ->latest('updated_at');

        if ($request->user()) {
            $query->where('user_id', $request->user()->id);
        }

        $projects = $query->paginate($request->get('per_page', 15));
        return response()->json(AnalysisProjectResource::collection($projects)->response()->getData(true));
    }

    // =========================================================================
    // GET /api/projects/{id}
    // =========================================================================

    public function show(int $id): JsonResponse
    {
        $project = AnalysisProject::with('result')->findOrFail($id);
        return response()->json(['data' => new AnalysisProjectResource($project)]);
    }

    // =========================================================================
    // GET /api/projects/{id}/edit-data
    // =========================================================================

    /**
     * Payload khusus untuk form Edit Data Peta. Endpoint ini sengaja terpisah
     * dari show() agar frontend tidak bergantung pada bentuk resource umum.
     * Hanya metadata file yang dikirim, tidak ada absolute path server.
     */
    public function editData(Request $request, int $id): JsonResponse
    {
        $query = AnalysisProject::query();
        if ($request->user()) {
            $query->where('user_id', $request->user()->id);
        }

        $project = $query->findOrFail($id);

        $indicators = [
            'das' => $this->indicatorMetadata($project->das_path, 'Daerah Aliran Sungai (DAS)', 'SHP (.zip) / GeoJSON', true),
            'dem' => $this->indicatorMetadata($project->dem_path, 'Digital Elevation Model (DEM)', 'TIF', true),
            'tutupan_lahan' => $this->indicatorMetadata($project->landcover_path, 'Tutupan Lahan', 'TIF / SHP (.zip) / GeoJSON', true),
            'curah_hujan' => $this->indicatorMetadata($project->rainfall_path, 'Curah Hujan', 'TIF', true),
            'jenis_tanah' => $this->indicatorMetadata($project->soil_path, 'Jenis Tanah', 'TIF / SHP (.zip) / GeoJSON', true),
            'batas_wilayah' => $this->indicatorMetadata($project->admin_path, 'Batas Wilayah', 'SHP (.zip) / GeoJSON', false),
        ];

        return response()->json([
            'data' => [
                'id' => $project->id,
                'kode_project' => $project->project_code,
                'nama_project' => $project->project_name,
                'status' => $project->status,
                'file_input' => collect($indicators)->mapWithKeys(
                    fn (array $meta, string $key) => [$key => $meta['filename']]
                )->all(),
                'indicators' => $indicators,
                'updated_at' => $project->updated_at,
            ],
        ]);
    }

    // =========================================================================
    // GET /api/projects/{id}/result
    // =========================================================================

    public function result(int $id): JsonResponse
    {
        $project = AnalysisProject::findOrFail($id);
        $result = $project->result;

        if (!$result) {
            return response()->json(['message' => 'Hasil analisis belum tersedia.'], 404);
        }

        return response()->json(['data' => new AnalysisResultResource($result)]);
    }

    // =========================================================================
    // GET /api/projects/{id}/table
    // =========================================================================

    public function table(int $id): JsonResponse
    {
        $project = AnalysisProject::findOrFail($id);
        $result = $project->result;

        if (!$result) {
            return response()->json(['message' => 'Data tabel belum tersedia.'], 404);
        }

        $zones = AnalysisResultZone::with(['village', 'plantRecommendationRule'])
            ->where('result_id', $result->id)
            ->get();

        $mappedData = $zones->map(function (AnalysisResultZone $z) {
            return [
                'zone_id' => $z->zone_id,
                'kota_kabupaten' => $z->kabupaten,
                'kecamatan' => $z->kecamatan,
                'desa_kelurahan' => $z->desa,
                'status_lahan_kritis' => $z->status_lahan_kritis,
                'skor_cpi_rata2' => $z->skor_cpi,
                'luas_ha' => $z->luas_ha,
                'score_landcover_rata2' => $z->score_landcover,
                'score_rainfall_rata2' => $z->score_rainfall,
                'score_soil_rata2' => $z->score_soil,
                'score_slope_rata2' => $z->score_slope,
                'slope_percent_rata2' => $z->slope_percent,
                'rekomendasi_intervensi' => $z->rekomendasi_intervensi,
                'rekomendasi_tanaman' => $z->rekomendasi_tanaman ?? [],
                'rekomendasi_tanaman_alasan' => $z->rekomendasi_tanaman_alasan,
                'rekomendasi_tanaman_rule' => $z->plantRecommendationRule ? [
                    'id' => $z->plantRecommendationRule->id,
                    'code' => $z->plantRecommendationRule->code,
                    'wilayah' => $z->plantRecommendationRule->region_name,
                    'kategori' => $z->plantRecommendationRule->category,
                    'fungsi_rhl' => $z->plantRecommendationRule->rhl_function,
                ] : null,
                'cdk' => $z->cdk,
                'nama_kelompok' => $z->nama_kelompok,
                'ketua_kelompok' => $z->ketua_kelompok,
                'status_kelayakan' => $z->status_kelayakan,
                'latitude' => $z->village?->latitude,
                'longitude' => $z->village?->longitude,
            ];
        });

        return response()->json(['data' => $mappedData]);
    }

    // =========================================================================
    // GET /api/projects/{id}/map
    // =========================================================================

    public function map(int $id): JsonResponse
    {
        $project = AnalysisProject::findOrFail($id);
        $result = $project->result;

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
                Log::warning('[AnalysisProjectController] Gagal proxy GeoJSON dari Python', [
                    'project_id' => $project->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if (!$geoJsonData) {
            return response()->json([
                'geojson_url' => $geojsonUrl,
                'message' => 'Akses GeoJSON melalui URL yang disediakan.',
            ]);
        }

        $zonesFromDb = AnalysisResultZone::where('result_id', $result->id)->get()->keyBy('zone_id');

        if (isset($geoJsonData['features']) && is_array($geoJsonData['features'])) {
            foreach ($geoJsonData['features'] as &$feature) {
                $zoneId = $feature['properties']['zone_id'] ?? null;
                if ($zoneId !== null && $zonesFromDb->has((string) $zoneId)) {
                    $dbZone = $zonesFromDb->get((string) $zoneId);
                    $feature['properties']['cdk'] = $dbZone->cdk;
                    $feature['properties']['nama_kelompok'] = $dbZone->nama_kelompok;
                    $feature['properties']['ketua_kelompok'] = $dbZone->ketua_kelompok;
                    $feature['properties']['rekomendasi_tanaman'] = $dbZone->rekomendasi_tanaman ?? [];
                    $feature['properties']['rekomendasi_tanaman_alasan'] = $dbZone->rekomendasi_tanaman_alasan;
                }
            }
            unset($feature);
        }

        return response()->json($geoJsonData);
    }

    // =========================================================================
    // GET /api/projects/{id}/download/{filename}
    // =========================================================================

    public function download(int $id, string $filename): BinaryFileResponse|JsonResponse
    {
        $project = AnalysisProject::findOrFail($id);
        $result = $project->result;

        if (!$result) {
            return response()->json(['message' => 'Hasil analisis belum tersedia.'], 404);
        }

        $jobId = $result->job_id;
        $safeFile = basename($filename);
        $pythonUrl = rtrim(config('cpi.engine_url', 'http://127.0.0.1:8001'), '/') .
            "/result/{$jobId}/file/{$safeFile}";

        try {
            $response = Http::timeout(120)
                ->sink($tmpPath = tempnam(sys_get_temp_dir(), 'cpi_'))
                ->get($pythonUrl);

            if ($response->failed()) {
                @unlink($tmpPath);
                return response()->json(['message' => 'File tidak ditemukan di Python service.'], 404);
            }

            return response()->download($tmpPath, $safeFile)->deleteFileAfterSend(true);
        } catch (Exception $e) {
            return response()->json(['message' => 'Gagal mengunduh file: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // GET /api/zones
    // =========================================================================

    public function zones(): JsonResponse
    {
        $villages = Village::with(['district.city'])->whereNotNull('geometry')->get();

        if ($villages->isEmpty()) {
            return response()->json(['type' => 'FeatureCollection', 'features' => []]);
        }

        $features = $villages->map(function ($village) {
            $geometry = is_string($village->geometry)
                ? json_decode($village->geometry, true)
                : $village->geometry;

            return [
                'type' => 'Feature',
                'properties' => [
                    'id' => $village->id,
                    'desa' => $village->name,
                    'kecamatan' => $village->district?->name,
                    'kabupaten' => $village->district?->city?->name,
                    'provinsi' => 'Jawa Barat',
                ],
                'geometry' => $geometry,
            ];
        })->filter(fn ($feature) => !empty($feature['geometry']))->values();

        return response()->json(['type' => 'FeatureCollection', 'features' => $features]);
    }

    // =========================================================================
    // GET /api/projects/{id}/zones
    // =========================================================================

    public function zonesList(int $id): JsonResponse
    {
        $project = AnalysisProject::findOrFail($id);
        $result = $project->result;

        if (!$result) {
            return response()->json(['message' => 'Hasil analisis belum tersedia.'], 404);
        }

        $zones = AnalysisResultZone::with(['fieldValidations', 'village', 'plantRecommendationRule'])
            ->where('result_id', $result->id)
            ->get();

        return response()->json(['data' => $zones]);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function analysisValidationRules(bool $edit): array
    {
        $requiredFile = $edit ? 'sometimes|file' : 'required|file';
        $optionalFile = 'sometimes|nullable|file';

        return [
            'nama_project' => 'nullable|string|max:255',
            'dem' => "{$requiredFile}|mimes:tif,tiff|max:" . self::MAX_UPLOAD_KB,
            'tutupan_lahan' => "{$requiredFile}|mimes:tif,tiff,zip,geojson,json|max:" . self::MAX_UPLOAD_KB,
            'curah_hujan' => "{$requiredFile}|mimes:tif,tiff|max:" . self::MAX_UPLOAD_KB,
            'jenis_tanah' => "{$requiredFile}|mimes:tif,tiff,zip,geojson,json|max:" . self::MAX_UPLOAD_KB,
            'das' => "{$requiredFile}|mimes:zip,geojson,json|max:" . self::MAX_UPLOAD_KB,
            'kemiringan' => "{$optionalFile}|mimes:tif,tiff|max:" . self::MAX_UPLOAD_KB,
            'batas_wilayah' => "{$optionalFile}|mimes:zip,geojson,json|max:" . self::MAX_UPLOAD_KB,
            'target_resolution' => 'nullable|numeric|min:100|max:10000',
            'save_intermediate' => 'nullable|boolean',
            'ahp_matrix' => 'nullable|json',
        ];
    }

    private function runCpi(Request $request, AnalysisProject $project, array $paths): array
    {
        $ahpMatrix = $request->input('ahp_matrix')
            ? json_decode($request->input('ahp_matrix'), true)
            : null;
        $targetResolution = $request->input('target_resolution')
            ? (float) $request->input('target_resolution')
            : 5000;
        $saveIntermediate = filter_var($request->input('save_intermediate', false), FILTER_VALIDATE_BOOLEAN);

        $finalAdminPath = $paths['admin_path'] ?: $this->buildLocalAdminGeoJson($project->project_code);

        return $this->cpiService->analyze(
            projectId: $project->project_code,
            demPath: $paths['dem_path'],
            landcoverPath: $paths['landcover_path'],
            rainfallPath: $paths['rainfall_path'],
            soilPath: $paths['soil_path'],
            dasPath: $paths['das_path'],
            adminPath: $finalAdminPath,
            targetResolution: $targetResolution,
            saveIntermediate: $saveIntermediate,
            ahpMatrix: $ahpMatrix,
            zoneApiUrl: null,
            historyApiUrl: null,
        );
    }

    private function persistAnalysisRevision(AnalysisProject $project, array $pythonResponse, array $projectUpdates): AnalysisResult
    {
        $tableRows = $this->applyPlantRecommendations($pythonResponse['table'] ?? []);
        $pythonResponse['table'] = $tableRows;
        $mapData = $pythonResponse['map'] ?? [];

        return DB::transaction(function () use ($project, $pythonResponse, $tableRows, $mapData, $projectUpdates) {
            $result = AnalysisResult::create([
                'project_id' => $project->id,
                'job_id' => $pythonResponse['job_id'],
                'status' => $pythonResponse['status'] ?? 'completed',
                'result_json' => $pythonResponse,
                'table_json' => $tableRows,
                'metadata_json' => $pythonResponse['ahp'] ?? [],
                'critical_geojson_url' => $mapData['critical_geojson'] ?? null,
                'map_html_url' => $mapData['html_preview'] ?? null,
                'cpi_raster_url' => $mapData['cpi_raster'] ?? null,
                'class_raster_url' => $mapData['class_raster'] ?? null,
            ]);

            $this->saveZoneBreakdown($result, $tableRows);

            $project->update(array_merge($projectUpdates, [
                'status' => AnalysisProject::STATUS_COMPLETED,
                'python_job_id' => $pythonResponse['job_id'],
                'error_message' => null,
            ]));

            return $result;
        });
    }

    private function applyPlantRecommendations(array $tableRows): array
    {
        return array_map(function (array $row) {
            $recommendation = $this->plantRecommendationService->recommend($row);
            $row['plant_recommendation_rule_id'] = $recommendation['rule_id'];
            $row['rekomendasi_tanaman'] = $recommendation['plants'];
            $row['rekomendasi_tanaman_alasan'] = $recommendation['reason'];
            $row['rekomendasi_tanaman_rule'] = [
                'code' => $recommendation['rule_code'],
                'wilayah' => $recommendation['region_name'],
                'kategori' => $recommendation['category'],
                'fungsi_rhl' => $recommendation['rhl_function'],
                'keterangan' => $recommendation['notes'],
                'match_score' => $recommendation['match_score'],
            ];
            return $row;
        }, $tableRows);
    }

    private function buildLocalAdminGeoJson(string $projectCode): ?string
    {
        $villages = Village::with(['district.city'])->whereNotNull('geometry')->get();
        if ($villages->isEmpty()) {
            return null;
        }

        $features = $villages->map(function ($village) {
            $geometry = is_string($village->geometry) ? json_decode($village->geometry, true) : $village->geometry;
            return [
                'type' => 'Feature',
                'properties' => [
                    'id' => $village->id,
                    'desa' => $village->name,
                    'kecamatan' => $village->district?->name,
                    'kabupaten' => $village->district?->city?->name,
                    'provinsi' => 'Jawa Barat',
                ],
                'geometry' => $geometry,
            ];
        })->filter(fn ($feature) => !empty($feature['geometry']))->values()->toArray();

        if (!$features) {
            return null;
        }

        $relativePath = "projects/{$projectCode}/zones_master_local.geojson";
        Storage::disk('local')->put($relativePath, json_encode([
            'type' => 'FeatureCollection',
            'features' => $features,
        ], JSON_UNESCAPED_UNICODE));

        return Storage::disk('local')->path($relativePath);
    }

    private function indicatorMetadata(?string $path, string $label, string $format, bool $required): array
    {
        $normalizedPath = $path ? str_replace('\\', '/', $path) : null;
        $exists = $path ? is_file($path) : false;
        $filename = $normalizedPath ? basename($normalizedPath) : null;
        $sizeBytes = $exists ? filesize($path) : null;
        $extension = $filename ? strtolower(pathinfo($filename, PATHINFO_EXTENSION)) : null;

        return [
            'label' => $label,
            'filename' => $filename,
            'extension' => $extension,
            'format' => $format,
            'required' => $required,
            'exists' => $exists,
            'size_bytes' => $sizeBytes,
            'size_mb' => $sizeBytes !== null ? round($sizeBytes / 1024 / 1024, 2) : null,
        ];
    }

    private function storeFile($file, string $directory, string $prefix): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = $prefix . '_' . now()->format('Ymd_His_u') . '_' . Str::random(6) . '.' . $extension;
        $relativePath = $file->storeAs($directory, $filename, 'local');
        return Storage::disk('local')->path($relativePath);
    }

    private function projectPaths(AnalysisProject $project): array
    {
        return [
            'dem_path' => $project->dem_path,
            'landcover_path' => $project->landcover_path,
            'rainfall_path' => $project->rainfall_path,
            'soil_path' => $project->soil_path,
            'das_path' => $project->das_path,
            'admin_path' => $project->admin_path,
        ];
    }

    private function deleteLocalAbsoluteFile(?string $path): void
    {
        if (!$path || !is_file($path)) {
            return;
        }

        $root = realpath(Storage::disk('local')->path('')) ?: Storage::disk('local')->path('');
        $realPath = realpath($path);
        if ($realPath && str_starts_with($realPath, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            $file = new \SplFileInfo($realPath);
            @unlink($realPath);

            // Python mengekstrak ZIP dengan pola <nama_zip>_extracted. Saat upload
            // revisi gagal, bersihkan juga folder ekstraksinya agar tidak menyisakan
            // artifact orphan.
            $extractDir = $file->getPath() . DIRECTORY_SEPARATOR . $file->getBasename('.' . $file->getExtension()) . '_extracted';
            if (is_dir($extractDir)) {
                $relative = ltrim(str_replace(rtrim($root, DIRECTORY_SEPARATOR), '', $extractDir), DIRECTORY_SEPARATOR);
                Storage::disk('local')->deleteDirectory($relative);
            }
        }
    }

    private function handleInitialAnalysisFailure(Exception $e, AnalysisProject $project, string $baseDir): JsonResponse
    {
        $status = $this->safeHttpStatus($e);

        if ($status === 422) {
            Storage::disk('local')->deleteDirectory($baseDir);
            $project->delete();
        } else {
            $project->update([
                'status' => AnalysisProject::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
        }

        Log::error('[AnalysisProjectController] Analisis gagal', [
            'project_code' => $project->project_code,
            'error' => $e->getMessage(),
            'http_status' => $status,
        ]);

        return response()->json([
            'message' => $status === 422
                ? 'Validasi koordinat/lokasi data GIS gagal. Analisis dibatalkan.'
                : 'Analisis gagal diproses.',
            'error' => $e->getMessage(),
            'project_id' => $status === 422 ? null : $project->id,
            'project_code' => $project->project_code,
        ], $status);
    }

    private function safeHttpStatus(Exception $e): int
    {
        $code = (int) $e->getCode();
        return $code >= 400 && $code <= 499 ? $code : 500;
    }

    private function saveZoneBreakdown(AnalysisResult $result, array $tableRows): void
    {
        if (empty($tableRows)) {
            return;
        }

        $semuaKth = Kth::all();

        $zones = array_map(function ($row) use ($result, $semuaKth) {
            $kabupaten = $row['kota_kabupaten'] ?? $row['kabupaten'] ?? null;
            $kecamatan = $row['kecamatan'] ?? null;
            $desa = $row['desa_kelurahan'] ?? $row['desa'] ?? null;
            $cdk = $kabupaten ? $this->determineCdk($kabupaten) : null;

            $kthTerpilih = null;
            if ($desa && $kecamatan) {
                $kthTerpilih = $semuaKth->first(function ($k) use ($desa, $kecamatan) {
                    return strtolower((string) $k->desa_kelurahan) === strtolower((string) $desa)
                        && strtolower((string) $k->kecamatan) === strtolower((string) $kecamatan);
                });

                if (!$kthTerpilih) {
                    $kthTerpilih = $semuaKth->first(function ($k) use ($kecamatan) {
                        return strtolower((string) $k->kecamatan) === strtolower((string) $kecamatan);
                    });
                }
            }

            return [
                'result_id' => $result->id,
                'zone_id' => $row['zone_id'] ?? null,
                'provinsi' => $row['provinsi'] ?? null,
                'kabupaten' => $kabupaten,
                'kecamatan' => $kecamatan,
                'desa' => $desa,
                'skor_cpi' => $row['skor_cpi_rata2'] ?? $row['skor_cpi'] ?? null,
                'status_lahan_kritis' => $row['status_lahan_kritis'] ?? null,
                'luas_ha' => $row['luas_ha'] ?? null,
                'score_landcover' => $row['score_landcover_rata2'] ?? null,
                'score_rainfall' => $row['score_rainfall_rata2'] ?? null,
                'score_soil' => $row['score_soil_rata2'] ?? null,
                'score_slope' => $row['score_slope_rata2'] ?? null,
                'slope_percent' => $row['slope_percent_rata2'] ?? null,
                'alasan_skor' => $row['alasan_skor'] ?? null,
                'riwayat_intervensi' => $row['riwayat_intervensi'] ?? null,
                'rekomendasi_intervensi' => $row['rekomendasi_intervensi'] ?? null,
                'plant_recommendation_rule_id' => $row['plant_recommendation_rule_id'] ?? null,
                'rekomendasi_tanaman' => isset($row['rekomendasi_tanaman'])
                    ? json_encode(array_values((array) $row['rekomendasi_tanaman']), JSON_UNESCAPED_UNICODE)
                    : null,
                'rekomendasi_tanaman_alasan' => $row['rekomendasi_tanaman_alasan'] ?? null,
                'cdk' => $cdk,
                'nama_kelompok' => $kthTerpilih?->nama,
                'ketua_kelompok' => $kthTerpilih?->ketua,
                'status_validasi_penyuluh' => 'Belum',
                'status_kelayakan' => 'Belum Diverifikasi',
                'created_at' => now(),
                'updated_at' => now(),
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

<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CpiEngineService
 *
 * Bertanggung jawab memanggil Python CPI Engine via HTTP.
 * PHP tidak melakukan kalkulasi spasial apapun — semua dilakukan oleh Python.
 *
 * Flow:
 * 1. PHP menyimpan file ke storage terlebih dahulu.
 * 2. PHP memanggil service ini dengan path absolut ke setiap file.
 * 3. Service mengirim POST JSON ke Python endpoint `/analysis/path`.
 * 4. Python memproses dan mengembalikan job_id, peta, tabel, metadata.
 */
class CpiEngineService
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        // Ambil dari .env, default ke port 8001 agar tidak konflik dengan PHP di 8000
        $this->baseUrl = rtrim(config('cpi.engine_url', 'http://127.0.0.1:8001'), '/');
        $this->timeout = (int) config('cpi.engine_timeout', 600);
    }

    /**
     * Kirim request analisis ke Python CPI Engine menggunakan path file.
     * Ini adalah metode utama yang dipanggil dari controller.
     *
     * @param  string      $projectId           ID tracking project
     * @param  string      $demPath             Path absolut file DEM raster
     * @param  string      $landcoverPath       Path absolut file tutupan lahan
     * @param  string      $rainfallPath        Path absolut file curah hujan
     * @param  string      $soilPath            Path absolut file jenis tanah
     * @param  string      $dasPath             Path absolut file DAS
     * @param  string|null $adminPath           Path absolut batas wilayah (opsional)
     * @param  float|null  $targetResolution    Resolusi target dalam meter (default 5000 untuk tes)
     * @param  bool        $saveIntermediate    Simpan raster antara (false untuk produksi)
     * @param  array|null  $ahpMatrix           Matrix pairwise AHP custom (opsional)
     * @param  string|null $zoneApiUrl          URL PHP untuk master zonasi (opsional)
     * @param  string|null $historyApiUrl       URL PHP untuk historical intervensi (opsional)
     * @param  string|null $rulesApiUrl         URL PHP untuk rules kustom (opsional)
     * @return array                            Response penuh dari Python CPI Engine
     *
     * @throws Exception Jika request gagal atau Python mengembalikan error
     */
    public function analyze(
        string $projectId,
        string $demPath,
        string $landcoverPath,
        string $rainfallPath,
        string $soilPath,
        string $dasPath,
        ?string $adminPath = null,
        ?float $targetResolution = 5000,
        bool $saveIntermediate = false,
        ?array $ahpMatrix = null,
        ?string $zoneApiUrl = null,
        ?string $historyApiUrl = null,
        ?string $rulesApiUrl = null,
    ): array {
        $endpoint = $this->baseUrl . '/analysis/path';

        // Bangun payload sesuai PRD Section 5
        $payload = [
            'project_id'               => $projectId,
            'dem'                      => $this->normalizePath($demPath),
            'landcover'                => $this->normalizePath($landcoverPath),
            'rainfall'                 => $this->normalizePath($rainfallPath),
            'soil'                     => $this->normalizePath($soilPath),
            'das'                      => $this->normalizePath($dasPath),
            'save_intermediate_rasters' => $saveIntermediate,
        ];

        // Field opsional — hanya tambahkan jika ada nilainya
        if ($adminPath !== null) {
            $payload['admin'] = $this->normalizePath($adminPath);
        }
        if ($targetResolution !== null) {
            $payload['target_resolution'] = $targetResolution;
        }
        if ($ahpMatrix !== null) {
            $payload['ahp_matrix'] = $ahpMatrix;
        }
        if ($zoneApiUrl !== null) {
            $payload['zone_api_url'] = $zoneApiUrl;
        }
        if ($historyApiUrl !== null) {
            $payload['history_api_url'] = $historyApiUrl;
        }
        if ($rulesApiUrl !== null) {
            $payload['rules_api_url'] = $rulesApiUrl;
        }

        Log::info('[CpiEngineService] Mengirim request ke Python', [
            'endpoint'   => $endpoint,
            'project_id' => $projectId,
            'payload'    => $payload,
        ]);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['Accept' => 'application/json'])
                ->post($endpoint, $payload);

            if ($response->failed()) {
                $errorBody = $response->json() ?? $response->body();
                $detail = is_array($errorBody) ? ($errorBody['detail'] ?? $errorBody) : $errorBody;
                $errorMsg = is_array($detail) ? json_encode($detail) : $detail;

                Log::error('[CpiEngineService] Python mengembalikan error HTTP', [
                    'status'     => $response->status(),
                    'project_id' => $projectId,
                    'error'      => $errorMsg,
                ]);

                throw new Exception(
                    "Python CPI Engine mengembalikan error HTTP {$response->status()}: {$errorMsg}"
                );
            }

            $result = $response->json();

            Log::info('[CpiEngineService] Python berhasil menyelesaikan analisis', [
                'project_id' => $projectId,
                'job_id'     => $result['job_id'] ?? 'N/A',
                'status'     => $result['status'] ?? 'N/A',
            ]);

            return $result;

        } catch (ConnectionException $e) {
            Log::error('[CpiEngineService] Tidak dapat terhubung ke Python service', [
                'endpoint'   => $endpoint,
                'project_id' => $projectId,
                'message'    => $e->getMessage(),
            ]);

            throw new Exception(
                "Tidak dapat terhubung ke Python CPI Engine di {$endpoint}. " .
                "Pastikan service Python sudah berjalan. Detail: {$e->getMessage()}"
            );

        } catch (RequestException $e) {
            Log::error('[CpiEngineService] HTTP request exception', [
                'project_id' => $projectId,
                'message'    => $e->getMessage(),
            ]);

            throw new Exception("HTTP request error: {$e->getMessage()}");
        }
    }

    /**
     * Cek apakah Python CPI Engine sedang berjalan (health check).
     *
     * @return bool True jika service aktif
     */
    public function isHealthy(): bool
    {
        try {
            $response = Http::timeout(10)->get($this->baseUrl . '/health');
            return $response->successful() && ($response->json()['status'] ?? '') === 'ok';
        } catch (Exception $e) {
            Log::warning('[CpiEngineService] Health check gagal', ['message' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Normalisasi path agar kompatibel dengan OS tempat Python berjalan.
     * Menggunakan forward-slash yang didukung Python di Windows maupun Linux.
     */
    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}

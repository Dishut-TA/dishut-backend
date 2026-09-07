<?php
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RankController;
use App\Http\Controllers\Api\PegawaiController;
use App\Http\Controllers\Api\KabidDashboardController;
use App\Http\Controllers\Api\UserDonationDashboardController;
use App\Http\Controllers\AnalysisProjectController;
use App\Http\Controllers\Api\DonationProgramController;
use App\Http\Controllers\Api\LaporanDanaController;
use App\Http\Controllers\Api\ZonasiController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\DistrictController;
use App\Http\Controllers\InterventionRecommendationController;
use App\Http\Controllers\InterventionTypeController;
use App\Http\Controllers\LandController;
use App\Http\Controllers\SeedController;
use App\Http\Controllers\VillageController;
use App\Http\Controllers\Api\KabidPdasDashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/debug-upload', function (Request $request) {
    return response()->json([
        'post_max_size' => ini_get('post_max_size'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'content_type' => $request->header('Content-Type'),
        'all_input' => $request->all(),
        'all_files' => $request->allFiles(),
        'post_size' => $_SERVER['CONTENT_LENGTH'] ?? 0,
    ]);
});

// ============================================================================
// Master Data (wilayah, lahan, intervensi, dll.)
// ============================================================================
Route::apiResource('kabkotas', CityController::class);
Route::apiResource('kecamatans', DistrictController::class);
Route::apiResource('desas', VillageController::class);
Route::apiResource('lahans', LandController::class);
Route::apiResource('bibits', SeedController::class);
Route::apiResource('jenis-intervensis', InterventionTypeController::class);
Route::apiResource('rekomendasi-intervensis', InterventionRecommendationController::class);

// ============================================================================
// CPI Analysis Projects
// Endpoint ini dikonsumsi oleh Frontend untuk seluruh fitur analisis lahan kritis.
// Endpoint /api/zones juga dikonsumsi Python CPI Engine (zone_api_url parameter).
// ============================================================================
// Modul Analisis CPI & Rekomendasi Intervensi
// ============================================================================
Route::post('projects/upload', [\App\Http\Controllers\AnalysisProjectController::class, 'upload']);
Route::get('projects', [\App\Http\Controllers\AnalysisProjectController::class, 'index']);
Route::get('projects/{id}', [\App\Http\Controllers\AnalysisProjectController::class, 'show']);
Route::get('projects/{id}/result', [\App\Http\Controllers\AnalysisProjectController::class, 'result']);
Route::get('projects/{id}/table', [\App\Http\Controllers\AnalysisProjectController::class, 'table']);
Route::get('projects/{id}/map', [\App\Http\Controllers\AnalysisProjectController::class, 'map']);
Route::get('projects/{id}/download/{filename}', [\App\Http\Controllers\AnalysisProjectController::class, 'download']);
Route::get('projects/{id}/zones', [\App\Http\Controllers\AnalysisProjectController::class, 'zonesList']);
Route::get('projects/{id}/report-rurhl', [\App\Http\Controllers\Api\FieldValidationController::class, 'reportRurhl']);

// Monitoring Dashboard
Route::get('monitoring/dashboard', [\App\Http\Controllers\Api\MonitoringController::class, 'dashboard']);

// Evaluasi
Route::get('evaluasi/dashboard-stats', [\App\Http\Controllers\Api\EvaluasiController::class, 'dashboardStats']);
Route::get('evaluasi', [\App\Http\Controllers\Api\EvaluasiController::class, 'index']);
Route::get('evaluasi/{id}', [\App\Http\Controllers\Api\EvaluasiController::class, 'show']);
Route::post('evaluasi/{id}/submit', [\App\Http\Controllers\Api\EvaluasiController::class, 'submit']);
Route::post('evaluasi/{id}/selesaikan', [\App\Http\Controllers\Api\EvaluasiController::class, 'selesaikan']);

// Penugasan Evaluasi (Inisiasi oleh Staff PDAS)
Route::get('penugasan-evaluasi/programs', [\App\Http\Controllers\Api\PenugasanEvaluasiController::class, 'programs']);
Route::apiResource('penugasan-evaluasi', \App\Http\Controllers\Api\PenugasanEvaluasiController::class)->only(['index', 'store', 'show']);
Route::put('/penugasan-evaluasi/{id}/mulai', [\App\Http\Controllers\Api\PenugasanEvaluasiController::class, 'mulaiEvaluasi']);
Route::get('/penugasan-evaluasi-perhitungan', [\App\Http\Controllers\Api\PenugasanEvaluasiController::class, 'listPerhitungan']);
Route::put('/penugasan-evaluasi/{id}/faktual', [\App\Http\Controllers\Api\PenugasanEvaluasiController::class, 'saveFaktual']);
Route::put('/penugasan-evaluasi/{id}/kalkulasi', [\App\Http\Controllers\Api\PenugasanEvaluasiController::class, 'kalkulasiEvaluasi']);
Route::post('/penugasan-evaluasi/{id}/tindak-lanjut', [\App\Http\Controllers\Api\PenugasanEvaluasiController::class, 'submitTindakLanjut']);
Route::get('/penugasan-evaluasi-laporan-kabid', [\App\Http\Controllers\Api\PenugasanEvaluasiController::class, 'listLaporanKabid']);
Route::put('/penugasan-evaluasi/{id}/sahkan', [\App\Http\Controllers\Api\PenugasanEvaluasiController::class, 'sahkanLaporan']);
Route::put('/penugasan-evaluasi/{id}/revisi', [\App\Http\Controllers\Api\PenugasanEvaluasiController::class, 'revisiLaporan']);

// Pelaksanaan Penanaman & Penugasan
Route::middleware('auth:sanctum')->group(function () {
    Route::get('rehabilitasi/valid-zones', [\App\Http\Controllers\Api\RehabilitasiController::class, 'getValidZones']);
    Route::post('rehabilitasi/submit/{zoneId}', [\App\Http\Controllers\Api\RehabilitasiController::class, 'submitRencana']);

    Route::post('penugasan', [\App\Http\Controllers\Api\PenugasanController::class, 'store']);
    Route::get('penugasan', [\App\Http\Controllers\Api\PenugasanController::class, 'index']);
    Route::get('penugasan/me', [\App\Http\Controllers\Api\PenugasanController::class, 'myPenugasan']);
    Route::get('penugasan/kth-saya', [\App\Http\Controllers\Api\PenugasanController::class, 'myKthPenugasan']);
    Route::get('penugasan/dashboard', [\App\Http\Controllers\Api\PenugasanController::class, 'dashboard']);
    Route::get('penugasan/{id}', [\App\Http\Controllers\Api\PenugasanController::class, 'show']);
    Route::post('penugasan/{id}/mulai', [\App\Http\Controllers\Api\PenugasanController::class, 'mulaiPelaksanaan']);
    Route::post('penugasan/{id}/submit', [\App\Http\Controllers\Api\PenugasanController::class, 'submitPelaksanaan']);
    Route::post('penugasan/{id}/approve', [\App\Http\Controllers\Api\PenugasanController::class, 'approvePelaksanaan']);
    Route::post('penugasan/{id}/tugaskan-monitoring', [\App\Http\Controllers\Api\PenugasanController::class, 'storeMonitoring']);
    Route::post('penugasan/{id}/hentikan', [\App\Http\Controllers\Api\PenugasanController::class, 'hentikanPenugasan']);
    Route::post('penugasan/{id}/submit-monitoring', [\App\Http\Controllers\Api\PenugasanController::class, 'submitMonitoring']);
    Route::post('penugasan/{id}/submit-tindak-lanjut', [\App\Http\Controllers\Api\PenugasanController::class, 'submitTindakLanjut']);
    Route::get('penugasan/{id}/seeds', [\App\Http\Controllers\Api\PenugasanController::class, 'getSeeds']);
    
    // Dokumentasi
    Route::get('penugasan/{id}/dokumentasi', [\App\Http\Controllers\Api\PenugasanController::class, 'getDokumentasi']);
    Route::post('penugasan/{id}/dokumentasi', [\App\Http\Controllers\Api\PenugasanController::class, 'storeDokumentasi']);

    // Petak Ukur & Tanaman
    Route::get('penugasan/{id}/petak-ukur', [\App\Http\Controllers\Api\PetakUkurController::class, 'index']);
    Route::post('petak-ukur', [\App\Http\Controllers\Api\PetakUkurController::class, 'store']);
    Route::get('petak-ukur/{id}/tanaman', [\App\Http\Controllers\Api\PetakUkurController::class, 'getTanaman']);
    Route::post('petak-ukur/{id}/tanaman', [\App\Http\Controllers\Api\PetakUkurController::class, 'storeTanaman']);
    Route::put('tanaman/{id}', [\App\Http\Controllers\Api\PetakUkurController::class, 'updateTanaman']);
});


// Validasi Lapangan (Penyuluh & Kabid)
    Route::get('field-validations', [\App\Http\Controllers\Api\FieldValidationController::class, 'index']);
    Route::post('field-validations', [\App\Http\Controllers\Api\FieldValidationController::class, 'store']);
    Route::put('field-validations/{id}/verify', [\App\Http\Controllers\Api\FieldValidationController::class, 'verify']);

// Master Data
Route::get('zones', [\App\Http\Controllers\AnalysisProjectController::class, 'zones']); // Endpoint internal untuk Python
Route::get('interventions/{zone_id}', [\App\Http\Controllers\InterventionRecommendationController::class, 'history']);

Route::get('/zonasis', [ZonasiController::class, 'index']);
Route::post('/zonasis/upload', [ZonasiController::class, 'upload']);

// ============================================================================
// Modul Donasi
// ============================================================================
Route::get('bibits/{id}/detail', [SeedController::class, 'getBibitById']);
Route::get('admin/dashboard', [\App\Http\Controllers\Api\AdminDashboardController::class, 'index']);
Route::get('/dashboard-kabid', [KabidDashboardController::class, 'index']);
Route::post('kths/import', [\App\Http\Controllers\Api\KthController::class, 'importExcel']);
Route::post('donation-programs/{id}/upload-bast', [DonationProgramController::class, 'uploadBast']);
// Alias getById
$resources = [
    'donors' => \App\Http\Controllers\Api\DonorController::class,
    'kths' => \App\Http\Controllers\Api\KthController::class,
    'seed-specifications' => \App\Http\Controllers\Api\SeedSpecificationController::class,
    'donation-programs' => \App\Http\Controllers\Api\DonationProgramController::class,
    'donations' => \App\Http\Controllers\Api\DonationController::class,
    'transactions' => \App\Http\Controllers\Api\TransactionController::class,
    'planted-seeds' => \App\Http\Controllers\Api\PlantedSeedController::class,
    'reports' => \App\Http\Controllers\Api\ReportController::class,
];

foreach ($resources as $uri => $controller) {
    Route::get("{$uri}/{id}/getById", [$controller, 'getById']);
    Route::apiResource($uri, $controller);
}

// Dashboard KABID PDAS
Route::get('/dashboard/kabid', [KabidPdasDashboardController::class, 'getKabidPdasDashboard']);

// ============================================================================
// Modul Rehabilitasi APBD & CSR (Sesuai PRD)
// ============================================================================
Route::apiResource('csrs', \App\Http\Controllers\Api\CsrController::class);
Route::apiResource('program-apbds', \App\Http\Controllers\Api\ProgramApbdController::class);
Route::apiResource('program-csrs', \App\Http\Controllers\Api\ProgramCsrController::class);
Route::apiResource('transaksi-csrs', \App\Http\Controllers\Api\TransaksiCsrController::class);
Route::apiResource('laporan-danas', LaporanDanaController::class);
Route::put('laporan-danas/{id}/status', [LaporanDanaController::class, 'updateStatus']);
Route::apiResource('laporan-proyeks', \App\Http\Controllers\Api\LaporanProyekController::class);
Route::apiResource('dokumens', \App\Http\Controllers\Api\DokumenController::class);

// --- Merged from dishut-service-users-main ---


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    // Public routes
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user/donation-dashboard', [UserDonationDashboardController::class, 'index']);
});

// Jabatan CRUD Routes (Public)
Route::prefix('jabatan')->group(function () {
    Route::get('/', [PositionController::class, 'index']);
    Route::post('/', [PositionController::class, 'store']);
    Route::get('/{id}', [PositionController::class, 'show']);
    Route::put('/{id}', [PositionController::class, 'update']);
    Route::delete('/{id}', [PositionController::class, 'destroy']);
});

// Pangkat CRUD Routes (Public)
Route::prefix('pangkats')->group(function () {
    Route::get('/', [RankController::class, 'index']);
    Route::post('/', [RankController::class, 'store']);
    Route::get('/{id}', [RankController::class, 'show']);
    Route::put('/{id}', [RankController::class, 'update']);
    Route::delete('/{id}', [RankController::class, 'destroy']);
});

// Permission CRUD Routes (Public)
Route::prefix('permissions')->group(function () {
    Route::get('/', [PermissionController::class, 'index']);
    Route::post('/', [PermissionController::class, 'store']);
    Route::get('/{id}', [PermissionController::class, 'show']);
    Route::put('/{id}', [PermissionController::class, 'update']);
    Route::delete('/{id}', [PermissionController::class, 'destroy']);
});

// Role CRUD Routes (Public)
Route::prefix('roles')->group(function () {
    Route::get('/', [RoleController::class, 'index']);
    Route::post('/', [RoleController::class, 'store']);
    Route::get('/{id}', [RoleController::class, 'show']);
    Route::put('/{id}', [RoleController::class, 'update']);
    Route::delete('/{id}', [RoleController::class, 'destroy']);
    Route::post('/{id}/permissions', [RoleController::class, 'assignPermissionToRole']);
});

// User Routes (Public)
Route::prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::post('/', [UserController::class, 'store']);
    Route::get('/{id}', [UserController::class, 'showUserWithRolesAndPermissions']);
    Route::put('/{id}', [UserController::class, 'update']);
    Route::delete('/{id}', [UserController::class, 'destroy']);
    Route::post('/{id}/roles', [UserController::class, 'assignRoleToUser']);
});

// Pegawai / Profiles Routes (Public)
Route::prefix('pegawais')->group(function () {
    Route::get('/', [PegawaiController::class, 'index']);
    Route::get('/{id}', [PegawaiController::class, 'show']);
    Route::put('/{id}', [PegawaiController::class, 'update']);
    Route::post('/{id}', [PegawaiController::class, 'update']); // Sometimes FormData needs POST with _method=PUT
});

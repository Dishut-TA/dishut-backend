<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AnalysisResultZone;
use App\Models\DonationProgram;
use App\Models\ProgramApbd;
use App\Models\ProgramCsr;
use App\Models\Penugasan;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PenugasanController extends Controller
{
    /**
     * Menampilkan daftar semua program yang siap ditugaskan
     * Menggabungkan data dari Validasi Lokasi (CPI) dan Pelaksanaan Penanaman
     */
    public function index(Request $request): JsonResponse
    {
        $penugasans = Penugasan::with('penyuluh')->get()->keyBy(function($item) {
            return $item->penugasanable_type . '_' . $item->penugasanable_id;
        });

        $data = [];

        // 1. Data Validasi Lokasi (dari AnalysisResultZone)
        $zones = AnalysisResultZone::with('fieldValidations')->get();
        foreach ($zones as $zone) {
            $key = AnalysisResultZone::class . '_' . $zone->id;
            $penugasan = $penugasans->get($key);
            
            $data[] = [
                'id' => $zone->id,
                'source_type' => AnalysisResultZone::class,
                'program' => 'Analisis Lahan Kritis - ' . $zone->desa,
                'lokasi' => $zone->desa . ', ' . $zone->kecamatan . ', ' . $zone->kabupaten,
                'jenisKegiatan' => 'Validasi Lokasi',
                'wilayah' => $zone->kabupaten,
                'rencanaPeriode' => '-',
                'penyuluh' => $penugasan && $penugasan->penyuluh ? $penugasan->penyuluh->username : '-',
                'penyuluh_id' => $penugasan ? $penugasan->penyuluh_id : null,
                'penugasan_id' => $penugasan ? $penugasan->id : null,
                'status' => $penugasan ? $penugasan->status : 'Menunggu Penugasan',
                'tanggalPenugasan' => $penugasan ? $penugasan->tanggal_penugasan : '-',
                'created_at' => $penugasan ? $penugasan->created_at : $zone->created_at,
                'detail' => $zone // embed detail data
            ];
        }

        // 2. Data Pelaksanaan Lapangan (Donasi)
        // Hanya ambil yang sudah diverifikasi Kabid (status = 'Aktif')
        $donations = DonationProgram::with(['kth', 'analysisResultZone', 'seeds'])->where('status', 'Aktif')->get();
        foreach ($donations as $don) {
            $key = DonationProgram::class . '_' . $don->id;
            $penugasan = $penugasans->get($key);
            
            $year = $don->created_at ? $don->created_at->format('Y') : date('Y');
            $formattedId = 'P-DNS-' . $year . '-' . str_pad($don->id, 3, '0', STR_PAD_LEFT);

            $data[] = [
                'id' => $formattedId,
                'original_id' => $don->id,
                'source_type' => DonationProgram::class,
                'program' => $don->name,
                'lokasi' => $don->location,
                'jenisKegiatan' => $penugasan ? $penugasan->jenis_kegiatan : 'Pelaksanaan Penanaman',
                'wilayah' => $don->analysisResultZone ? $don->analysisResultZone->kabupaten : '-',
                'rencanaPeriode' => 'P0',
                'penyuluh' => $penugasan && $penugasan->penyuluh ? $penugasan->penyuluh->username : '-',
                'penyuluh_id' => $penugasan ? $penugasan->penyuluh_id : null,
                'penugasan_id' => $penugasan ? $penugasan->id : null,
                'status' => $penugasan ? $penugasan->status : 'Menunggu Penugasan',
                'tanggalPenugasan' => $penugasan ? $penugasan->tanggal_penugasan : '-',
                'created_at' => $penugasan ? $penugasan->created_at : $don->created_at,
                'detail' => $don
            ];
        }

        // 3. Data Pelaksanaan Lapangan (APBD)
        $apbds = ProgramApbd::with(['kth', 'analysisResultZone'])->get();
        foreach ($apbds as $apbd) {
            $key = ProgramApbd::class . '_' . $apbd->id;
            $penugasan = $penugasans->get($key);
            
            $lokasi = $apbd->kth ? ($apbd->kth->desa_kelurahan . ', ' . $apbd->kth->kecamatan . ', ' . $apbd->kth->kabupaten_kota) : '-';
            $wilayah = $apbd->kth ? $apbd->kth->kabupaten_kota : '-';

            $year = $apbd->created_at ? $apbd->created_at->format('Y') : date('Y');
            $formattedId = 'P-ABD-' . $year . '-' . str_pad($apbd->id, 3, '0', STR_PAD_LEFT);

            $data[] = [
                'id' => $formattedId,
                'original_id' => $apbd->id,
                'source_type' => ProgramApbd::class,
                'program' => $apbd->nama_program,
                'lokasi' => $lokasi,
                'jenisKegiatan' => $penugasan ? $penugasan->jenis_kegiatan : 'Pelaksanaan Penanaman',
                'wilayah' => $wilayah,
                'rencanaPeriode' => 'P0',
                'penyuluh' => $penugasan && $penugasan->penyuluh ? $penugasan->penyuluh->username : '-',
                'penyuluh_id' => $penugasan ? $penugasan->penyuluh_id : null,
                'penugasan_id' => $penugasan ? $penugasan->id : null,
                'status' => $penugasan ? $penugasan->status : 'Menunggu Penugasan',
                'tanggalPenugasan' => $penugasan ? $penugasan->tanggal_penugasan : '-',
                'created_at' => $penugasan ? $penugasan->created_at : $apbd->created_at,
                'detail' => $apbd
            ];
        }

        // 4. Data Pelaksanaan Lapangan (CSR)
        $csrs = ProgramCsr::with(['kth', 'analysisResultZone'])->get();
        foreach ($csrs as $csr) {
            $key = ProgramCsr::class . '_' . $csr->id;
            $penugasan = $penugasans->get($key);
            
            $lokasi = $csr->kth ? ($csr->kth->desa_kelurahan . ', ' . $csr->kth->kecamatan . ', ' . $csr->kth->kabupaten_kota) : '-';
            $wilayah = $csr->kth ? $csr->kth->kabupaten_kota : '-';

            $year = $csr->created_at ? $csr->created_at->format('Y') : date('Y');
            $formattedId = 'P-CSR-' . $year . '-' . str_pad($csr->id, 3, '0', STR_PAD_LEFT);

            $data[] = [
                'id' => $formattedId,
                'original_id' => $csr->id,
                'source_type' => ProgramCsr::class,
                'program' => $csr->nama_program,
                'lokasi' => $lokasi,
                'jenisKegiatan' => $penugasan ? $penugasan->jenis_kegiatan : 'Pelaksanaan Penanaman',
                'wilayah' => $wilayah,
                'rencanaPeriode' => 'P0',
                'penyuluh' => $penugasan && $penugasan->penyuluh ? $penugasan->penyuluh->username : '-',
                'penyuluh_id' => $penugasan ? $penugasan->penyuluh_id : null,
                'penugasan_id' => $penugasan ? $penugasan->id : null,
                'status' => $penugasan ? $penugasan->status : 'Menunggu Penugasan',
                'tanggalPenugasan' => $penugasan ? $penugasan->tanggal_penugasan : '-',
                'created_at' => $penugasan ? $penugasan->created_at : $csr->created_at,
                'detail' => $csr
            ];
        }

        return response()->json([
            'message' => 'Berhasil mengambil daftar penugasan',
            'data' => $data
        ]);
    }

    /**
     * Menyimpan data penugasan baru ke penyuluh
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'penyuluh_id' => 'required|exists:users,id',
            'source_type' => 'required|string',
            'source_id' => 'required|integer',
            'jenis_kegiatan' => 'required|string',
            'tanggal_mulai' => 'nullable|date',
            'batas_waktu' => 'nullable|date',
            'arahan' => 'nullable|string',
            'prioritas' => 'nullable|string'
        ]);

        $penugasan = Penugasan::updateOrCreate(
            [
                'penugasanable_type' => $request->source_type,
                'penugasanable_id' => $request->source_id,
            ],
            [
                'penyuluh_id' => $request->penyuluh_id,
                'jenis_kegiatan' => $request->jenis_kegiatan,
                'status' => 'Ditugaskan',
                'tanggal_penugasan' => date('Y-m-d'),
                'tanggal_mulai' => $request->tanggal_mulai,
                'batas_waktu' => $request->batas_waktu,
                'arahan' => $request->arahan,
                'prioritas' => $request->prioritas
            ]
        );

        // Populate Petak Ukur based on AnalysisResultZone
        $jumlah_pu = 0;
        if ($penugasan->penugasanable && method_exists($penugasan->penugasanable, 'analysisResultZone') && $penugasan->penugasanable->analysisResultZone) {
            $jumlah_pu = $penugasan->penugasanable->analysisResultZone->jumlah_pu ?? 0;
        } elseif ($penugasan->penugasanable_type === 'App\\Models\\AnalysisResultZone') {
            $jumlah_pu = $penugasan->penugasanable->jumlah_pu ?? 0;
        }

        if ($jumlah_pu > 0) {
            for ($i = 1; $i <= $jumlah_pu; $i++) {
                \App\Models\PetakUkur::firstOrCreate([
                    'penugasan_id' => $penugasan->id,
                    'nama' => "PU $i"
                ], [
                    'status' => 'Belum Dibuat',
                    'polygon_data' => '[]'
                ]);
            }
        }

        return response()->json([
            'message' => 'Penugasan berhasil disimpan',
            'data' => $penugasan
        ], 201);
    }

    /**
     * Mengambil daftar penugasan milik penyuluh yang sedang login
     */
    public function myPenugasan(Request $request): JsonResponse
    {
        $penugasans = Penugasan::with(['penyuluh.kth', 'petakUkurs.dataTanamans', 'penugasanable' => function (MorphTo $morphTo) {
            $morphTo->morphWith([
                AnalysisResultZone::class => ['fieldValidations'],
                DonationProgram::class => ['analysisResultZone', 'seeds', 'kth'],
                ProgramApbd::class => ['analysisResultZone', 'kth'],
                ProgramCsr::class => ['analysisResultZone', 'kth']
            ]);
        }])
            ->where('penyuluh_id', $request->user()->id)
            ->get();

        return response()->json([
            'message' => 'Berhasil mengambil data penugasan saya',
            'data' => $penugasans
        ]);
    }

        /**
     * Mengambil daftar penugasan milik KTH (akun KTH Pelaksanaan) yang sedang login.
     * Sumber program dicari lewat kth_id pada DonationProgram / ProgramApbd / ProgramCsr
     * yang terhubung dengan Kth milik user yang login.
     */
    public function myKthPenugasan(Request $request): JsonResponse
    {
        $kth = $request->user()->kth;

        if (!$kth) {
            return response()->json([
                'message' => 'Akun ini belum terhubung dengan data KTH manapun',
                'data' => []
            ], 404);
        }

        $donationIds = DonationProgram::where('kth_id', $kth->id)->pluck('id');
        $apbdIds = ProgramApbd::where('kth_id', $kth->id)->pluck('id');
        $csrIds = ProgramCsr::where('kth_id', $kth->id)->pluck('id');

        $penugasans = Penugasan::with(['penyuluh', 'penugasanable' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    DonationProgram::class => ['analysisResultZone', 'seeds', 'kth'],
                    ProgramApbd::class => ['analysisResultZone', 'kth'],
                    ProgramCsr::class => ['analysisResultZone', 'kth'],
                ]);
            }])
            ->where(function ($q) use ($donationIds, $apbdIds, $csrIds) {
                $q->where(function ($qq) use ($donationIds) {
                    $qq->where('penugasanable_type', DonationProgram::class)
                       ->whereIn('penugasanable_id', $donationIds);
                })->orWhere(function ($qq) use ($apbdIds) {
                    $qq->where('penugasanable_type', ProgramApbd::class)
                       ->whereIn('penugasanable_id', $apbdIds);
                })->orWhere(function ($qq) use ($csrIds) {
                    $qq->where('penugasanable_type', ProgramCsr::class)
                       ->whereIn('penugasanable_id', $csrIds);
                });
            })
            ->whereIn('jenis_kegiatan', ['Pelaksanaan Penanaman', 'Tindak Lanjut'])
            ->orderByDesc('created_at')
            ->get();

        $data = $penugasans->map(function ($p) {
            $source = $p->penugasanable;

            $namaProgram = '-';
            $lokasi = '-';
            $sumberDana = '-';
            $targetBibit = 0;
            $jenisTanaman = '-';

            if ($source instanceof DonationProgram) {
                $namaProgram = $source->name;
                $lokasi = $source->location ?? '-';
                $sumberDana = 'Donasi';
                $targetBibit = $source->total_seeds_collected ?? 0;
                $jenisTanaman = $source->seeds && $source->seeds->count()
                    ? $source->seeds->pluck('name')->filter()->implode(', ')
                    : '-';
            } elseif ($source instanceof ProgramApbd) {
                $namaProgram = $source->nama_program;
                $lokasi = $source->kth
                    ? ($source->kth->desa_kelurahan . ', ' . $source->kth->kecamatan . ', ' . $source->kth->kabupaten_kota)
                    : '-';
                $sumberDana = 'APBD';
                $targetBibit = $source->jumlah_bibit ?? 0;
            } elseif ($source instanceof ProgramCsr) {
                $namaProgram = $source->nama_program;
                $lokasi = $source->lokasi
                    ?? ($source->kth ? ($source->kth->desa_kelurahan . ', ' . $source->kth->kecamatan . ', ' . $source->kth->kabupaten_kota) : '-');
                $sumberDana = 'CSR';
                $targetBibit = $source->jumlah_bibit ?? 0;
                $jenisTanaman = $source->jenis_tanaman ?? '-';
            }

            $jenisPenugasan = $p->jenis_kegiatan === 'Tindak Lanjut' ? 'Penyulaman' : 'Penanaman';

            $mulai = $p->tanggal_mulai ? \Carbon\Carbon::parse($p->tanggal_mulai) : null;
            $selesai = $p->batas_waktu ? \Carbon\Carbon::parse($p->batas_waktu) : null;

            return [
                'id' => $p->id,
                'program' => $namaProgram,
                'lokasi' => $lokasi,
                'jenisPenugasan' => $jenisPenugasan,
                'sumberProgram' => $sumberDana,
                'periode' => ($mulai && $selesai)
                    ? $mulai->translatedFormat('M') . ' - ' . $selesai->translatedFormat('M Y')
                    : '-',
                'target' => $targetBibit ? $targetBibit . ' Pohon' : '-',
                'status' => $p->status,
                'keterangan' => trim(
                    ($mulai ? 'Mulai ' . $mulai->translatedFormat('M Y') : '') .
                    ($selesai ? "\ns.d. " . $selesai->translatedFormat('M Y') : '')
                ) ?: '-',
                'penyuluh' => $p->penyuluh->username ?? '-',
                'rincian' => $jenisPenugasan === 'Penanaman' ? [
                    'targetTanaman' => $targetBibit ? $targetBibit . ' Pohon' : '-',
                    'jenisTanaman' => $jenisTanaman,
                    'sumberBibit' => $sumberDana,
                ] : [
                    'targetPenyulaman' => $targetBibit ? $targetBibit . ' Pohon' : '-',
                    'alasan' => $p->arahan ?? 'Sesuai arahan penyuluh',
                    'jenisTanaman' => $jenisTanaman,
                ],
            ];
        });

        return response()->json([
            'message' => 'Berhasil mengambil data penugasan KTH',
            'data' => $data
        ]);
    }

    /**
     * GET /api/penugasan/dashboard
     * Statistik agregat untuk Dashboard Pelaksanaan & Monitoring
     * (Nama method harus "dashboard" karena itu yang dipanggil routes/api.php)
     */
    public function dashboard(): JsonResponse
    {
        $penugasans = Penugasan::with(['penyuluh', 'penugasanable'])->get();

        $totalProgram = $penugasans->count();
        $berjalan = $penugasans->where('status', 'Berjalan')->count();
        $selesai = $penugasans->where('status', 'Selesai')->count();
        $menunggu = $penugasans->where('status', 'Menunggu Penugasan')->count();

        // Hitung target & realisasi bibit dari semua sumber
        $totalTargetBibit = 0;
        $totalRealisasiBibit = 0;
        $programList = [];
        $perWilayah = [];

        foreach ($penugasans as $p) {
            $source = $p->penugasanable;
            $targetBibit = 0;
            $realisasiBibit = 0;
            $wilayah = '-';
            $namaProgram = '-';
            $lokasi = '-';
            $sumberDana = '-';

            if ($source instanceof DonationProgram) {
                $targetBibit = $source->total_seeds_collected ?? 0;
                $realisasiBibit = $source->total_seeds_realized ?? 0;
                $namaProgram = $source->name;
                $lokasi = $source->location;
                $sumberDana = 'Donasi';
                $wilayah = $source->analysisResultZone ? $source->analysisResultZone->kabupaten : '-';
            } elseif ($source instanceof ProgramApbd) {
                $targetBibit = $source->jumlah_bibit ?? 0;
                $realisasiBibit = 0; // Belum ada field realisasi di APBD
                $namaProgram = $source->nama_program;
                $lokasi = $source->lokasi ?? '-';
                $sumberDana = 'APBD';
            } elseif ($source instanceof ProgramCsr) {
                $targetBibit = $source->jumlah_bibit ?? 0;
                $realisasiBibit = 0; // Belum ada field realisasi di CSR
                $namaProgram = $source->nama_program;
                $lokasi = $source->lokasi ?? '-';
                $sumberDana = 'CSR';
                $wilayah = $source->kabupaten ?? '-';
            }

            $totalTargetBibit += $targetBibit;
            $totalRealisasiBibit += $realisasiBibit;

            // Per wilayah aggregation
            if ($wilayah && $wilayah !== '-') {
                if (!isset($perWilayah[$wilayah])) {
                    $perWilayah[$wilayah] = ['target' => 0, 'realisasi' => 0, 'program' => 0];
                }
                $perWilayah[$wilayah]['target'] += $targetBibit;
                $perWilayah[$wilayah]['realisasi'] += $realisasiBibit;
                $perWilayah[$wilayah]['program'] += 1;
            }

            $programList[] = [
                'id' => $p->id,
                'nama_program' => $namaProgram,
                'lokasi' => $lokasi,
                'sumber_dana' => $sumberDana,
                'wilayah' => $wilayah,
                'status' => $p->status,
                'penyuluh' => $p->penyuluh ? ($p->penyuluh->nama_pengguna ?? $p->penyuluh->name) : '-',
                'tanggal_penugasan' => $p->tanggal_penugasan,
                'target_bibit' => $targetBibit,
                'realisasi_bibit' => $realisasiBibit,
                'jenis_kegiatan' => $p->jenis_kegiatan,
            ];
        }

        // Hitung juga yang belum ditugaskan
        $donasiBelumTugas = DonationProgram::where('status', 'Aktif')
            ->whereDoesntHave('penugasans')
            ->count();
        $apbdBelumTugas = ProgramApbd::whereDoesntHave('penugasans')->count();
        $csrBelumTugas = ProgramCsr::whereDoesntHave('penugasans')->count();

        $persentaseRealisasi = $totalTargetBibit > 0 
            ? round(($totalRealisasiBibit / $totalTargetBibit) * 100, 2) 
            : 0;

        return response()->json([
            'message' => 'Berhasil mengambil data dashboard penugasan.',
            'stats' => [
                'total_program' => $totalProgram,
                'berjalan' => $berjalan,
                'selesai' => $selesai,
                'menunggu_penugasan' => $menunggu + $donasiBelumTugas + $apbdBelumTugas + $csrBelumTugas,
                'total_target_bibit' => $totalTargetBibit,
                'total_realisasi_bibit' => $totalRealisasiBibit,
                'persentase_realisasi' => $persentaseRealisasi,
            ],
            'per_wilayah' => $perWilayah,
            'programs' => $programList,
        ]);
    }

    /**
     * Menampilkan detail satu penugasan
     */
    public function show($id): JsonResponse
    {
        // Coba cari berdasarkan ID langsung dulu
        $model = Penugasan::with(['penyuluh.kth', 'petakUkurs.dataTanamans', 'dokumentasi', 'penugasanable' => function (MorphTo $morphTo) {
            $morphTo->morphWith([
                AnalysisResultZone::class => ['fieldValidations'],
                DonationProgram::class => ['kth', 'analysisResultZone', 'seeds'],
                ProgramApbd::class => ['analysisResultZone', 'kth'],
                ProgramCsr::class => ['analysisResultZone', 'kth']
            ]);
        }])->find($id);

        if (!$model) {
            // Fallback cari berdasarkan penugasanable_id
            $query = Penugasan::with(['penyuluh.kth', 'petakUkurs.dataTanamans', 'dokumentasi', 'penugasanable' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    AnalysisResultZone::class => ['fieldValidations'],
                    DonationProgram::class => ['kth', 'analysisResultZone', 'seeds'],
                    ProgramApbd::class => ['analysisResultZone', 'kth'],
                    ProgramCsr::class => ['analysisResultZone', 'kth']
                ]);
            }]);

            if (AnalysisResultZone::find($id)) {
                $model = (clone $query)->where('penugasanable_type', AnalysisResultZone::class)
                    ->where('penugasanable_id', $id)
                    ->first();
            }
            if (!$model && DonationProgram::find($id)) {
                $model = (clone $query)->where('penugasanable_type', DonationProgram::class)
                    ->where('penugasanable_id', $id)
                    ->first();
            }
            if (!$model && ProgramApbd::find($id)) {
                $model = (clone $query)->where('penugasanable_type', ProgramApbd::class)
                    ->where('penugasanable_id', $id)
                    ->first();
            }
            if (!$model && ProgramCsr::find($id)) {
                $model = (clone $query)->where('penugasanable_type', ProgramCsr::class)
                    ->where('penugasanable_id', $id)
                    ->first();
            }
        }
        
        $penugasan = $model;

        if (!$penugasan) {
            return response()->json(['message' => 'Penugasan tidak ditemukan'], 404);
        }

        // Jika ini adalah penugasan Monitoring atau Tindak Lanjut, Petak Ukurnya 
        // berada di penugasan Pelaksanaan Penanaman. Kita perlu menyalinnya ke response.
        if (in_array($penugasan->jenis_kegiatan, ['Monitoring', 'Tindak Lanjut']) && $penugasan->petakUkurs->isEmpty()) {
            $pelaksanaan = Penugasan::with('petakUkurs.dataTanamans')
                ->where('penugasanable_type', $penugasan->penugasanable_type)
                ->where('penugasanable_id', $penugasan->penugasanable_id)
                ->where('jenis_kegiatan', 'Pelaksanaan Penanaman')
                ->first();
                
            if ($pelaksanaan && $pelaksanaan->petakUkurs) {
                // Attach as an attribute so it gets serialized
                $penugasan->setRelation('petakUkurs', $pelaksanaan->petakUkurs);
            }
        }

        // Format data untuk UI
        $source = $penugasan->penugasanable;
        $namaProgram = '-';
        $lokasi = '-';
        $sumberDana = '-';
        $ketuaKth = '-';
        
        if ($source instanceof \App\Models\AnalysisResultZone) {
            $namaProgram = 'Rehabilitasi Lahan ' . ($source->desa ?? '-');
            $lokasi = 'Desa ' . ($source->desa ?? '-') . ', Kec. ' . ($source->kecamatan ?? '-') . ', Kab. ' . ($source->kabupaten ?? '-');
            $sumberDana = 'Donasi / Lahan Kritis';
            $ketuaKth = $source->ketua_kelompok ?? '-';
        } elseif ($source instanceof \App\Models\DonationProgram) {
            $namaProgram = $source->name;
            if ($source->analysisResultZone) {
                $lokasi = 'Desa ' . ($source->analysisResultZone->desa ?? '-') . ', Kec. ' . ($source->analysisResultZone->kecamatan ?? '-') . ', Kab. ' . ($source->analysisResultZone->kabupaten ?? '-');
            }
            $sumberDana = 'Donasi / Lahan Kritis';
            $ketuaKth = $source->kth ? $source->kth->ketua : '-';
        } elseif ($source instanceof \App\Models\ProgramApbd) {
            $namaProgram = $source->nama_program;
            $lokasi = $source->lokasi ?? '-';
            $sumberDana = 'APBD';
            $ketuaKth = $source->kth ? $source->kth->ketua : '-';
        } elseif ($source instanceof \App\Models\ProgramCsr) {
            $namaProgram = $source->nama_program;
            $lokasi = $source->lokasi ?? '-';
            $sumberDana = 'CSR';
            $ketuaKth = $source->kth ? $source->kth->ketua : '-';
        }

        $penugasan->setAttribute('formatted_data', [
            'nama_program' => $namaProgram,
            'lokasi' => $lokasi,
            'sumber_dana' => $sumberDana,
            'ketua_kth' => $ketuaKth,
            'periode_monitoring' => $penugasan->periode_monitoring ?? 'P1'
        ]);

        return response()->json([
            'message' => 'Detail penugasan',
            'data' => $penugasan
        ]);
    }

    /**
     * Memulai pelaksanaan (ubah status dari Ditugaskan menjadi Berjalan)
     */
    public function mulaiPelaksanaan($id): JsonResponse
    {
        $penugasan = Penugasan::find($id);
        
        if (!$penugasan) {
            return response()->json(['message' => 'Penugasan tidak ditemukan'], 404);
        }

        if ($penugasan->status === 'Ditugaskan') {
            $penugasan->status = 'Berjalan';
            $penugasan->tanggal_mulai = date('Y-m-d');
            $penugasan->save();
        }

        return response()->json([
            'message' => 'Status penugasan berhasil diperbarui menjadi Berjalan',
            'data' => $penugasan
        ]);
    }

    public function submitPelaksanaan(Request $request, $id): JsonResponse
    {
        $penugasan = Penugasan::findOrFail($id);
        
        $penugasan->update([
            'status' => 'Menunggu Verifikasi'
        ]);

        return response()->json([
            'message' => 'Laporan pelaksanaan berhasil dikirim untuk diverifikasi',
            'data' => $penugasan
        ]);
    }

    public function submitMonitoring(Request $request, $id): JsonResponse
    {
        $penugasan = Penugasan::findOrFail($id);
        
        $penugasan->update([
            'status' => 'Menunggu Evaluasi'
        ]);

        return response()->json([
            'message' => 'Laporan monitoring berhasil dikirim untuk dievaluasi',
            'data' => $penugasan
        ]);
    }

    public function approvePelaksanaan(Request $request, $id): JsonResponse
    {
        $penugasan = Penugasan::findOrFail($id);
        
        $penugasan->update([
            'status' => 'Selesai'
        ]);

        return response()->json([
            'message' => 'Laporan pelaksanaan berhasil disetujui',
            'data' => $penugasan
        ]);
    }

    public function storeMonitoring(Request $request, $id): JsonResponse
    {
        $request->validate([
            'periode_monitoring' => 'required|string',
            'tanggal_penugasan' => 'required|date',
            'batas_waktu' => 'required|date',
            'metode' => 'required|string',
            'prioritas' => 'required|string',
            'tujuan' => 'required|string',
            'arahan' => 'required|string',
        ]);

        $existingPenugasan = Penugasan::find($id);

        if (!$existingPenugasan) {
            // Fallback cari berdasarkan penugasanable_id
            $sourceType = null;
            if (DonationProgram::find($id)) {
                $sourceType = DonationProgram::class;
            } elseif (ProgramApbd::find($id)) {
                $sourceType = ProgramApbd::class;
            } elseif (ProgramCsr::find($id)) {
                $sourceType = ProgramCsr::class;
            }

            if ($sourceType) {
                $existingPenugasan = Penugasan::where('penugasanable_type', $sourceType)
                    ->where('penugasanable_id', $id)
                    ->whereNotNull('penyuluh_id')
                    ->first();
            }
        }

        if (!$existingPenugasan) {
            return response()->json(['message' => 'Penugasan atau Program tidak ditemukan'], 404);
        }

        $penugasan = Penugasan::create([
            'penugasanable_type' => $existingPenugasan->penugasanable_type,
            'penugasanable_id' => $existingPenugasan->penugasanable_id,
            'penyuluh_id' => $existingPenugasan->penyuluh_id,
            'jenis_kegiatan' => 'Monitoring', // Gunakan Monitoring, periode via kolom periode_monitoring
            'status' => 'Berjalan', // Default status Berjalan
            'tanggal_penugasan' => $request->tanggal_penugasan,
            'batas_waktu' => $request->batas_waktu,
            'periode_monitoring' => $request->periode_monitoring,
            'metode' => $request->metode,
            'prioritas' => $request->prioritas,
            'tujuan' => $request->tujuan,
            'arahan' => $request->arahan,
            // 'lampiran_penugasan' dihandle jika ada upload file
        ]);

        return response()->json([
            'message' => 'Penugasan monitoring berhasil dibuat.',
            'data' => $penugasan
        ], 201);
    }

    /**
     * Menghentikan sebuah program monitoring/tindak lanjut (status -> 'Dihentikan')
     * Dipakai oleh halaman Monitoring Program Rehabilitasi
     */
    public function hentikanPenugasan(Request $request, $id): JsonResponse
    {
        $penugasan = Penugasan::find($id);

        if (!$penugasan) {
            return response()->json(['message' => 'Penugasan tidak ditemukan'], 404);
        }

        $penugasan->update([
            'status' => 'Dihentikan',
            'arahan' => $request->alasan ? trim(($penugasan->arahan ?? '') . "\n[Dihentikan] " . $request->alasan) : $penugasan->arahan,
        ]);

        return response()->json([
            'message' => 'Program berhasil dihentikan',
            'data' => $penugasan
        ]);
    }

    public function getDokumentasi($id): JsonResponse
    {
        $dokumentasi = \App\Models\DokumentasiPenugasan::where('penugasan_id', $id)->get();
        return response()->json([
            'message' => 'Daftar Dokumentasi Penugasan',
            'data' => $dokumentasi
        ]);
    }

    public function getSeeds($id): JsonResponse
    {
        // For simplicity, returning all available seeds as references.
        // In reality, this would filter by the specific program's allocated seeds.
        $seeds = \App\Models\Seed::with('specification')->get();
        return response()->json([
            'message' => 'Daftar bibit referensi',
            'data' => $seeds
        ]);
    }

    public function storeDokumentasi(Request $request, $id): JsonResponse
    {
        $request->validate([
            'file' => 'required|image|max:5120',
            'jenis_dokumentasi' => 'nullable|string',
            'keterangan' => 'nullable|string'
        ]);

        $path = $request->file('file')->store('dokumentasi_penugasan', 'public');

        $dokumentasi = \App\Models\DokumentasiPenugasan::create([
            'penugasan_id' => $id,
            'file_path' => $path,
            'jenis_dokumentasi' => $request->jenis_dokumentasi,
            'keterangan' => $request->keterangan
        ]);

        return response()->json([
            'message' => 'Dokumentasi berhasil diunggah',
            'data' => $dokumentasi
        ], 201);
    }
}

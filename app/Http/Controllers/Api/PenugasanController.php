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
     * Daftar penugasan untuk menu Monitoring Program Rehabilitasi.
     *
     * Satu program bisa punya banyak penugasan (Pelaksanaan Penanaman, lalu
     * Monitoring P1, P2, sampai Tindak Lanjut). Karena itu penugasan dikelompokkan,
     * bukan di-keyBy: keyBy hanya menyimpan satu penugasan per program sehingga
     * riwayat periode monitoring hilang dan jenis kegiatan yang terbaca acak.
     *
     * Program yang belum punya penugasan tetap muncul satu baris berstatus
     * 'Menunggu Penugasan'.
     */
    public function index(Request $request): JsonResponse
    {
        $penugasans = Penugasan::with('penyuluh')
            ->orderBy('id')
            ->get()
            ->groupBy(function ($item) {
                return $item->penugasanable_type . '_' . $item->penugasanable_id;
            });

        $data = [];

        // 1. Data Validasi Lokasi (dari AnalysisResultZone)
        $zones = AnalysisResultZone::with('fieldValidations')->get();
        foreach ($zones as $zone) {
            $data = array_merge($data, $this->barisPenugasan(
                [
                    'id' => $zone->id,
                    'original_id' => $zone->id,
                    'source_type' => AnalysisResultZone::class,
                    'program' => 'Analisis Lahan Kritis - ' . $zone->desa,
                    'lokasi' => $zone->desa . ', ' . $zone->kecamatan . ', ' . $zone->kabupaten,
                    'wilayah' => $zone->kabupaten,
                    'rencanaPeriode' => '-',
                    'detail' => $zone,
                ],
                $penugasans->get(AnalysisResultZone::class . '_' . $zone->id),
                'Validasi Lokasi',
                $zone->created_at,
                true
            ));
        }

        // 2. Data Pelaksanaan Lapangan (Donasi)
        // Hanya ambil yang sudah diverifikasi Kabid (status = 'Aktif')
        $donations = DonationProgram::with(['kth', 'analysisResultZone', 'seeds'])->where('status', 'Aktif')->get();
        foreach ($donations as $don) {
            $year = $don->created_at ? $don->created_at->format('Y') : date('Y');

            $data = array_merge($data, $this->barisPenugasan(
                [
                    'id' => 'P-DNS-' . $year . '-' . str_pad($don->id, 3, '0', STR_PAD_LEFT),
                    'original_id' => $don->id,
                    'source_type' => DonationProgram::class,
                    'program' => $don->name,
                    'lokasi' => $don->location,
                    'wilayah' => $don->analysisResultZone ? $don->analysisResultZone->kabupaten : '-',
                    'rencanaPeriode' => 'P0',
                    'detail' => $don,
                ],
                $penugasans->get(DonationProgram::class . '_' . $don->id),
                'Pelaksanaan Penanaman',
                $don->created_at
            ));
        }

        // 3. Data Pelaksanaan Lapangan (APBD)
        $apbds = ProgramApbd::with(['kth', 'analysisResultZone'])->get();
        foreach ($apbds as $apbd) {
            $year = $apbd->created_at ? $apbd->created_at->format('Y') : date('Y');

            $data = array_merge($data, $this->barisPenugasan(
                [
                    'id' => 'P-ABD-' . $year . '-' . str_pad($apbd->id, 3, '0', STR_PAD_LEFT),
                    'original_id' => $apbd->id,
                    'source_type' => ProgramApbd::class,
                    'program' => $apbd->nama_program,
                    'lokasi' => $apbd->kth ? ($apbd->kth->desa_kelurahan . ', ' . $apbd->kth->kecamatan . ', ' . $apbd->kth->kabupaten_kota) : '-',
                    'wilayah' => $apbd->kth ? $apbd->kth->kabupaten_kota : '-',
                    'rencanaPeriode' => 'P0',
                    'detail' => $apbd,
                ],
                $penugasans->get(ProgramApbd::class . '_' . $apbd->id),
                'Pelaksanaan Penanaman',
                $apbd->created_at
            ));
        }

        // 4. Data Pelaksanaan Lapangan (CSR)
        $csrs = ProgramCsr::with(['kth', 'analysisResultZone'])->get();
        foreach ($csrs as $csr) {
            $year = $csr->created_at ? $csr->created_at->format('Y') : date('Y');

            $data = array_merge($data, $this->barisPenugasan(
                [
                    'id' => 'P-CSR-' . $year . '-' . str_pad($csr->id, 3, '0', STR_PAD_LEFT),
                    'original_id' => $csr->id,
                    'source_type' => ProgramCsr::class,
                    'program' => $csr->nama_program,
                    'lokasi' => $csr->kth ? ($csr->kth->desa_kelurahan . ', ' . $csr->kth->kecamatan . ', ' . $csr->kth->kabupaten_kota) : '-',
                    'wilayah' => $csr->kth ? $csr->kth->kabupaten_kota : '-',
                    'rencanaPeriode' => 'P0',
                    'detail' => $csr,
                ],
                $penugasans->get(ProgramCsr::class . '_' . $csr->id),
                'Pelaksanaan Penanaman',
                $csr->created_at
            ));
        }

        return response()->json([
            'message' => 'Berhasil mengambil daftar penugasan',
            'data' => $data
        ]);
    }

    /**
     * Membentuk baris daftar penugasan untuk satu program.
     *
     * Mengembalikan satu baris per penugasan, atau satu baris 'Menunggu Penugasan'
     * bila program belum pernah ditugaskan.
     *
     * @param array $base Kolom yang sama untuk semua baris program ini.
     * @param \Illuminate\Support\Collection|null $daftarPenugasan Penugasan milik program, urut id.
     * @param string $jenisDefault Jenis kegiatan yang ditampilkan bila belum ada penugasan.
     * @param mixed $createdAtFallback Tanggal program, dipakai bila belum ada penugasan.
     * @param bool $kunciJenis Paksa semua baris memakai $jenisDefault. Dipakai sumber
     *                         Validasi Lokasi, yang jenis kegiatannya ditentukan sumber
     *                         data dan bukan oleh isian bebas kolom jenis_kegiatan.
     */
    private function barisPenugasan(
        array $base,
        $daftarPenugasan,
        string $jenisDefault,
        $createdAtFallback,
        bool $kunciJenis = false
    ): array {
        $kunciProgram = $base['source_type'] . '_' . $base['original_id'];

        if (!$daftarPenugasan || $daftarPenugasan->isEmpty()) {
            return [array_merge($base, [
                'row_key' => $kunciProgram . '_belum',
                'jenisKegiatan' => $jenisDefault,
                'penyuluh' => '-',
                'penyuluh_id' => null,
                'penugasan_id' => null,
                'status' => 'Menunggu Penugasan',
                'tanggalPenugasan' => '-',
                'batasWaktu' => null,
                'periodeMonitoring' => null,
                'created_at' => $createdAtFallback,
            ])];
        }

        $rows = [];

        foreach ($daftarPenugasan as $penugasan) {
            $rows[] = array_merge($base, [
                'row_key' => $kunciProgram . '_' . $penugasan->id,
                'jenisKegiatan' => $kunciJenis ? $jenisDefault : $penugasan->jenis_kegiatan,
                'penyuluh' => $penugasan->penyuluh ? $penugasan->penyuluh->username : '-',
                'penyuluh_id' => $penugasan->penyuluh_id,
                'penugasan_id' => $penugasan->id,
                'status' => $penugasan->status,
                'tanggalPenugasan' => $penugasan->tanggal_penugasan,
                'batasWaktu' => $penugasan->batas_waktu,
                'periodeMonitoring' => $penugasan->periode_monitoring,
                'created_at' => $penugasan->created_at,
            ]);
        }

        return $rows;
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

        // Satu program bisa punya banyak penugasan, jadi jumlah penugasan bukan
        // jumlah program. Dihitung berdasarkan pasangan tipe + id program.
        $programDitugaskan = $penugasans
            ->map(fn ($p) => $p->penugasanable_type . '_' . $p->penugasanable_id)
            ->unique();

        $berjalan = $penugasans->where('status', 'Berjalan')->count();
        $selesai = $penugasans->where('status', 'Selesai')->count();
        $menunggu = $penugasans->where('status', 'Menunggu Penugasan')->count();

        // Hitung target & realisasi bibit dari semua sumber
        $totalTargetBibit = 0;
        $totalRealisasiBibit = 0;
        $programList = [];
        $perWilayah = [];
        $programSudahDihitung = [];

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

            // Target dan realisasi milik program, bukan milik penugasan. Tanpa
            // penjagaan ini, program dengan beberapa penugasan (Pelaksanaan,
            // Monitoring, Tindak Lanjut) terhitung berulang kali.
            $kunciProgram = $p->penugasanable_type . '_' . $p->penugasanable_id;
            $programBaru = !in_array($kunciProgram, $programSudahDihitung, true);

            if ($programBaru) {
                $programSudahDihitung[] = $kunciProgram;

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
            }

            $programList[] = [
                'id' => $p->id,
                // Penanda program, dipakai konsumen untuk menggabungkan baris
                // karena daftar ini berisi satu entri per penugasan.
                'program_key' => $p->penugasanable_type . '_' . $p->penugasanable_id,
                'program_id' => $p->penugasanable_id,
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

        // Rekapitulasi jumlah program per sumber dana, mencakup yang sudah
        // ditugaskan maupun belum, dihitung per program bukan per penugasan.
        $ditugaskanPerSumber = fn (string $kelas) => $programDitugaskan
            ->filter(fn ($kunci) => str_starts_with($kunci, $kelas . '_'))
            ->count();

        $perSumberDana = [
            'Donasi' => $ditugaskanPerSumber(DonationProgram::class) + $donasiBelumTugas,
            'APBD' => $ditugaskanPerSumber(ProgramApbd::class) + $apbdBelumTugas,
            'CSR' => $ditugaskanPerSumber(ProgramCsr::class) + $csrBelumTugas,
        ];

        $totalProgram = array_sum($perSumberDana);

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
            'per_sumber_dana' => $perSumberDana,
            'per_wilayah' => $perWilayah,
            'programs' => $programList,
            // Titik peta diambil dari polygon_data petak ukur yang digambar penyuluh.
            'map_locations' => \App\Support\PetaPetakUkur::titik(),
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
                DonationProgram::class => ['kth', 'analysisResultZone', 'seeds', 'donations'],
                ProgramApbd::class => ['analysisResultZone', 'kth'],
                ProgramCsr::class => ['analysisResultZone', 'kth']
            ]);
        }])->find($id);

        if (!$model) {
            // Fallback cari berdasarkan penugasanable_id
            $query = Penugasan::with(['penyuluh.kth', 'petakUkurs.dataTanamans', 'dokumentasi', 'penugasanable' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    AnalysisResultZone::class => ['fieldValidations'],
                    DonationProgram::class => ['kth', 'analysisResultZone', 'seeds', 'donations'],
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
            $pelaksanaan = Penugasan::with(['petakUkurs.dataTanamans', 'penyuluh', 'dokumentasi'])
                ->where('penugasanable_type', $penugasan->penugasanable_type)
                ->where('penugasanable_id', $penugasan->penugasanable_id)
                ->where('jenis_kegiatan', 'Pelaksanaan Penanaman')
                ->first();

            if ($pelaksanaan && $pelaksanaan->petakUkurs) {
                // Attach as an attribute so it gets serialized
                $penugasan->setRelation('petakUkurs', $pelaksanaan->petakUkurs);
                $penugasan->setAttribute('pelaksanaan_penanaman', $pelaksanaan);
            }

            // Dokumentasi lapangan diunggah pada penugasan Pelaksanaan Penanaman,
            // bukan pada penugasan Monitoring. Tanpa penurunan ini detail page
            // Monitoring selalu menampilkan dokumentasi kosong.
            if ($pelaksanaan && $penugasan->dokumentasi->isEmpty()) {
                $penugasan->setRelation('dokumentasi', $pelaksanaan->dokumentasi);
            }
        }

        // Seluruh dokumentasi program lintas penugasan, supaya halaman yang ingin
        // menampilkan riwayat foto lengkap tidak perlu menebak penugasan mana
        // yang menyimpannya.
        if ($penugasan->penugasanable_type && $penugasan->penugasanable_id) {
            $dokumentasiProgram = \App\Models\DokumentasiPenugasan::with('penugasan:id,jenis_kegiatan,periode_monitoring')
                ->whereHas('penugasan', function ($query) use ($penugasan) {
                    $query->where('penugasanable_type', $penugasan->penugasanable_type)
                        ->where('penugasanable_id', $penugasan->penugasanable_id);
                })
                ->orderBy('created_at')
                ->get();

            $penugasan->setAttribute('dokumentasi_program', $dokumentasiProgram);
        }

        // Ambil riwayat monitoring
        if ($penugasan->penugasanable_type && $penugasan->penugasanable_id) {
            $riwayat = Penugasan::with(['penyuluh'])
                ->where('penugasanable_type', $penugasan->penugasanable_type)
                ->where('penugasanable_id', $penugasan->penugasanable_id)
                ->whereIn('jenis_kegiatan', ['Monitoring', 'Tindak Lanjut'])
                ->orderBy('created_at', 'asc')
                ->get();
            $penugasan->setAttribute('riwayat_monitoring', $riwayat);
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

    public function submitTindakLanjut(Request $request, $id): JsonResponse
    {
        $penugasan = Penugasan::findOrFail($id);
        
        $penugasan->update([
            'status' => 'Monitoring Selesai'
        ]);

        return response()->json([
            'message' => 'Laporan tindak lanjut penyulaman berhasil dikirim',
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

        // PRD Feature 7: khusus program CSR, status 'Dihentikan' hanya boleh dipicu
        // oleh keputusan penghentian pendanaan di Modul Investasi CSR. Penyuluh maupun
        // Staff PDAS tidak boleh menghentikan monitoring CSR secara manual.
        if ($penugasan->penugasanable_type === \App\Models\ProgramCsr::class) {
            $program = $penugasan->penugasanable;

            if (!$program || $program->status !== 'Dihentikan') {
                return response()->json([
                    'message' => 'Monitoring program CSR tidak dapat dihentikan secara manual. '
                        . 'Penghentian hanya terjadi bila pihak pendana menghentikan pendanaan melalui Modul Investasi CSR.',
                ], 403);
            }
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

    /**
     * GET /api/penugasan/{id}/dokumentasi
     *
     * Query string 'scope=program' mengembalikan seluruh dokumentasi program
     * lintas penugasan. Tanpa itu, dokumentasi milik penugasan ini saja yang
     * dikembalikan, dengan penurunan dari penugasan Pelaksanaan Penanaman bila
     * penugasan Monitoring/Tindak Lanjut belum punya dokumentasi sendiri.
     */
    public function getDokumentasi(Request $request, $id): JsonResponse
    {
        $penugasan = Penugasan::find($id);

        if (!$penugasan) {
            return response()->json(['message' => 'Penugasan tidak ditemukan'], 404);
        }

        $milikProgram = \App\Models\DokumentasiPenugasan::with('penugasan:id,jenis_kegiatan,periode_monitoring')
            ->whereHas('penugasan', function ($query) use ($penugasan) {
                $query->where('penugasanable_type', $penugasan->penugasanable_type)
                    ->where('penugasanable_id', $penugasan->penugasanable_id);
            })
            ->orderBy('created_at');

        if ($request->query('scope') === 'program') {
            return response()->json([
                'message' => 'Seluruh dokumentasi program',
                'data' => $milikProgram->get()
            ]);
        }

        $dokumentasi = \App\Models\DokumentasiPenugasan::where('penugasan_id', $id)
            ->orderBy('created_at')
            ->get();

        // Dokumentasi lapangan tersimpan pada penugasan Pelaksanaan Penanaman.
        if ($dokumentasi->isEmpty() && in_array($penugasan->jenis_kegiatan, ['Monitoring', 'Tindak Lanjut'])) {
            $pelaksanaan = Penugasan::where('penugasanable_type', $penugasan->penugasanable_type)
                ->where('penugasanable_id', $penugasan->penugasanable_id)
                ->where('jenis_kegiatan', 'Pelaksanaan Penanaman')
                ->first();

            if ($pelaksanaan) {
                $dokumentasi = \App\Models\DokumentasiPenugasan::where('penugasan_id', $pelaksanaan->id)
                    ->orderBy('created_at')
                    ->get();
            }
        }

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




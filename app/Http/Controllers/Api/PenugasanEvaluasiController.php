<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Penugasan;
use App\Models\Evaluasi;
use App\Models\EvaluasiTim;
use Illuminate\Support\Facades\DB;

class PenugasanEvaluasiController extends Controller
{
    /**
     * GET /api/penugasan-evaluasi
     * List of penugasan evaluasi for Staff PDAS initiation dashboard.
     */
    public function index(Request $request): JsonResponse
    {
        // PERBAIKAN: Tambahkan evaluable.kth agar lokasi CSR/APBD terbaca
        $evaluasis = Evaluasi::with(['evaluable.kth', 'tim.user'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        $mappedData = $evaluasis->map(function ($eval) {
            $program = $eval->evaluable;
            $luas = 0;
            $jenisProgram = '-';
            $namaProyekLokasi = '-';
            
            if ($eval->evaluable_type === 'App\\Models\\ProgramApbd') {
                $jenisProgram = 'APBD';
                $luas = $program->target_luas_lahan ?? 0;
                $lokasi = $program->lokasi ?? ($program->kth ? ($program->kth->desa_kelurahan . ', ' . $program->kth->kabupaten_kota) : '');
                $namaProyekLokasi = ($program->nama_program ?? 'Program APBD') . ($lokasi ? ' - ' . $lokasi : '');
            } elseif ($eval->evaluable_type === 'App\\Models\\ProgramCsr') {
                $jenisProgram = 'CSR';
                $luas = $program->target_luas_lahan ?? 0;
                // PERBAIKAN: Ambil lokasi dari KTH untuk CSR
                $lokasi = $program->lokasi ?? ($program->kth ? ($program->kth->desa_kelurahan . ', ' . $program->kth->kabupaten_kota) : '');
                $namaProyekLokasi = ($program->nama_program ?? 'Program CSR') . ($lokasi ? ' - ' . $lokasi : '');
            } elseif ($eval->evaluable_type === 'App\\Models\\DonationProgram') {
                $jenisProgram = 'Donasi';
                $luas = $program->target_luas_lahan ?? 0;
                $namaProyekLokasi = ($program->name ?? 'Program Donasi') . ' - ' . ($program->location ?? '');
            }
            
            return [
                'id' => $eval->id,
                'nomor_surat' => $eval->nomor_surat,
                'nama_proyek_lokasi' => $namaProyekLokasi,
                'jenis_program' => $jenisProgram,
                'luas' => $luas,
                'tahap_evaluasi' => $eval->periode_evaluasi,
                'status_penugasan' => $eval->status,
                'created_at' => $eval->created_at,
                'tim' => $eval->tim, 
            ];
        });

        return response()->json([
            'message' => 'Daftar inisiasi penugasan evaluasi',
            'data' => $mappedData
        ]);
    }

    /**
     * GET /api/penugasan-evaluasi/programs
     * Get programs from monitoring that are ready for evaluation ("Menunggu Evaluasi").
     */
    public function programs(Request $request): JsonResponse
    {
        // Get penugasans monitoring that are Menunggu Evaluasi
        $penugasans = Penugasan::with('penugasanable')
            ->where('jenis_kegiatan', 'Monitoring')
            ->where('status', 'Menunggu Evaluasi')
            ->whereIn('penugasanable_type', [
                'App\\Models\\ProgramApbd',
                'App\\Models\\ProgramCsr'
            ])
            ->get();
            
        // Map to simpler format for frontend dropdown
        $programs = $penugasans->map(function ($p) {
            $program = $p->penugasanable;
            $nama = $program->nama_program ?? 'Program';
            $lokasi = $program->lokasi ?? 'Lokasi';
            $jenis = str_contains($p->penugasanable_type, 'ProgramApbd') ? 'APBD' : 'CSR';
            
            return [
                'evaluable_type' => $p->penugasanable_type,
                'evaluable_id' => $p->penugasanable_id,
                'nama_program' => $nama,
                'lokasi' => $lokasi,
                'jenis_program' => $jenis,
                'label' => "$nama - $lokasi ($jenis)",
                'program_detail' => $program,
            ];
        });

        return response()->json([
            'message' => 'Daftar program yang menunggu evaluasi',
            'data' => $programs
        ]);
    }

    /**
     * POST /api/penugasan-evaluasi
     * Create new assignment for evaluation team
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nomor_surat' => 'required|string|max:255',
            'tanggal_surat' => 'required|date',
            'evaluable_type' => 'required|string',
            'evaluable_id' => 'required|integer',
            'periode_evaluasi' => 'required|string', // e.g. Penanaman Awal (P0)
            'file_surat_tugas' => 'nullable|file|mimes:pdf|max:10240',
            'tim_penilai' => 'required|string', // because it's sent via FormData, typically it's a JSON string
        ]);

        DB::beginTransaction();
        try {
            $fileUrl = null;
            if ($request->hasFile('file_surat_tugas')) {
                $path = $request->file('file_surat_tugas')->store('evaluasi_surat_tugas', 'public');
                $fileUrl = url('storage/' . $path);
            }

            // Create Evaluasi record
            $evaluasi = Evaluasi::create([
                'nomor_surat' => $request->nomor_surat,
                'tanggal_surat' => $request->tanggal_surat,
                'evaluable_type' => $request->evaluable_type,
                'evaluable_id' => $request->evaluable_id,
                'periode_evaluasi' => $request->periode_evaluasi,
                'status' => 'Menunggu Pelaksanaan', // As requested
                'file_surat_tugas' => $fileUrl,
            ]);

            // Parse Tim Penilai JSON string
            $timPenilai = json_decode($request->tim_penilai, true);
            
            if (is_array($timPenilai)) {
                foreach ($timPenilai as $tim) {
                    EvaluasiTim::create([
                        'evaluasi_id' => $evaluasi->id,
                        'user_id' => $tim['user_id'],
                        'peran' => $tim['peran']
                    ]);
                }
            }
            
            DB::commit();

            return response()->json([
                'message' => 'Penugasan evaluasi berhasil dibuat.',
                'data' => $evaluasi->load('tim')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal membuat penugasan evaluasi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/penugasan-evaluasi/{id}
     * Get detail of a specific evaluation assignment.
     */
    public function show($id): JsonResponse
    {
        $evaluasi = Evaluasi::with(['evaluable', 'tim.user.pegawai'])->find($id);

        if (!$evaluasi) {
            return response()->json(['message' => 'Data penugasan evaluasi tidak ditemukan'], 404);
        }

        $program = $evaluasi->evaluable;
        $luas = 0;
        $targetBibit = 0;
        $jenisProgram = '-';
        $namaProyekLokasi = '-';
        
        // ==========================================
        // PERBAIKAN: Ambil CPI & Gunakan setAttribute
        // ==========================================
        // Load manual relasi CPI untuk memastikan datanya tertarik
        if ($program && method_exists($program, 'analysisResultZone')) {
            $program->loadMissing('analysisResultZone');
        }

        $skorCpi = 0;
        $rekomendasiCpi = 'Lakukan penyulaman (replanting) pada titik-titik petak ukur kritis menggunakan bibit yang sesuai spesifikasi.';

        if ($program && $program->analysisResultZone) {
            $skorCpi = $program->analysisResultZone->skor_cpi_rata2 ?? $program->analysisResultZone->skor_cpi ?? 0;
            $rekomendasiCpi = $program->analysisResultZone->rekomendasi_intervensi ?? $rekomendasiCpi;
        }

        // Wajib pakai setAttribute agar masuk ke response JSON
        $evaluasi->setAttribute('skor_cpi', $skorCpi);
        $evaluasi->setAttribute('rekomendasi_cpi', $rekomendasiCpi);
        // ==========================================

        if ($evaluasi->evaluable_type === 'App\\Models\\ProgramApbd') {
            $jenisProgram = 'APBD';
            $luas = $program->target_luas_lahan ?? 0;
            $targetBibit = $program->jumlah_bibit ?? 0;
            $lokasi = $program->lokasi ?? ($program->kth ? ($program->kth->desa_kelurahan . ', ' . $program->kth->kabupaten_kota) : '');
            $namaProyekLokasi = ($program->nama_program ?? 'Program APBD') . ($lokasi ? ' - ' . $lokasi : '');
        } elseif ($evaluasi->evaluable_type === 'App\\Models\\ProgramCsr') {
            $jenisProgram = 'CSR';
            $luas = $program->target_luas_lahan ?? 0;
            $targetBibit = $program->jumlah_bibit ?? 0;
            $lokasi = $program->lokasi ?? ($program->kth ? ($program->kth->desa_kelurahan . ', ' . $program->kth->kabupaten_kota) : '');
            $namaProyekLokasi = ($program->nama_program ?? 'Program CSR') . ($lokasi ? ' - ' . $lokasi : '');
        } elseif ($evaluasi->evaluable_type === 'App\\Models\\DonationProgram') {
            $jenisProgram = 'Donasi';
            $luas = $program->target_luas_lahan ?? 0;
            $targetBibit = $program->total_seeds_collected ?? ($program->target_seeds ?? 0);
            $namaProyekLokasi = ($program->name ?? 'Program Donasi') . ' - ' . ($program->location ?? '');
        }

        // Mapping informasi program ke dalam evaluasi
        $evaluasi->nama_proyek_lokasi = $namaProyekLokasi;
        $evaluasi->jenis_program = $jenisProgram;
        $evaluasi->luas = $luas;
        $evaluasi->target_bibit = $targetBibit; // Target rencana bibit awal program
        $evaluasi->tahap_evaluasi = $evaluasi->periode_evaluasi;
        $evaluasi->status_penugasan = $evaluasi->status; // Sinkronisasi status evaluasi

        // Ambil Petak Ukur dari tahap "Pelaksanaan Penanaman"
        $pelaksanaan = \App\Models\Penugasan::with('petakUkurs.dataTanamans')
            ->where('penugasanable_type', $evaluasi->evaluable_type)
            ->where('penugasanable_id', $evaluasi->evaluable_id)
            ->where('jenis_kegiatan', 'Pelaksanaan Penanaman')
            ->first();

        if ($pelaksanaan && $pelaksanaan->petakUkurs) {
            // Hitung akumulasi total bibit riil yang ditanam di tiap Petak Ukur
            $pelaksanaan->petakUkurs->each(function ($pu) {
                $pu->total_bibit_ditanam = $pu->dataTanamans->sum('jumlah');
            });
            $evaluasi->setRelation('petakUkurs', $pelaksanaan->petakUkurs);
        } else {
            $evaluasi->setRelation('petakUkurs', collect());
        }

        return response()->json([
            'message' => 'Detail penugasan evaluasi',
            'data' => $evaluasi
        ]);
    }

    /**
     * PUT /api/penugasan-evaluasi/{id}/mulai
     * Memulai pelaksanaan evaluasi (Ubah status dari Menunggu ke Sedang Evaluasi)
     */
    public function mulaiEvaluasi($id): JsonResponse
    {
        $evaluasi = Evaluasi::find($id);

        if (!$evaluasi) {
            return response()->json([
                'message' => 'Data penugasan evaluasi tidak ditemukan'
            ], 404);
        }

        // Hanya ubah status jika saat ini masih "Menunggu Pelaksanaan"
        if ($evaluasi->status === 'Menunggu Pelaksanaan') {
            $evaluasi->status = 'Sedang Evaluasi';
            $evaluasi->save();
        }

        return response()->json([
            'message' => 'Status evaluasi berhasil diperbarui menjadi Sedang Evaluasi',
            'data' => $evaluasi
        ]);
    }

    /**
     * GET /api/penugasan-evaluasi-perhitungan
     * Mengambil daftar evaluasi yang siap dihitung (Sedang Evaluasi / Selesai)
     */
    public function listPerhitungan(Request $request): JsonResponse
    {
        // Ambil evaluasi yang statusnya Sedang Evaluasi atau Selesai Evaluasi
        $evaluasis = Evaluasi::with(['evaluable', 'tim.user'])
            ->whereIn('status', ['Sedang Evaluasi', 'Selesai Evaluasi', 'Menunggu Verifikasi Hasil', 'Selesai'])
            ->orderBy('updated_at', 'desc')
            ->get();
            
        // PERBAIKAN: Tambahkan use ($request) di sini
        $mappedData = $evaluasis->map(function ($eval) use ($request) {
            $program = $eval->evaluable;
            $namaProyek = '-';
            $lokasi = '-';
            
            if ($eval->evaluable_type === 'App\\Models\\ProgramApbd') {
                $namaProyek = $program->nama_program ?? 'Program APBD';
                $lokasi = $program->lokasi ?? ($program->kth ? ($program->kth->desa_kelurahan . ', ' . $program->kth->kabupaten_kota) : '-');
            } elseif ($eval->evaluable_type === 'App\\Models\\ProgramCsr') {
                $namaProyek = $program->nama_program ?? 'Program CSR';
                $lokasi = $program->lokasi ?? ($program->kth ? ($program->kth->desa_kelurahan . ', ' . $program->kth->kabupaten_kota) : '-');
            } elseif ($eval->evaluable_type === 'App\\Models\\DonationProgram') {
                $namaProyek = $program->name ?? 'Program Donasi';
                $lokasi = $program->location ?? '-';
            }

            // Cari tahu peran user yang sedang login
            $peranSaya = 'Anggota Tim'; 
            $userId = $request->user() ? $request->user()->id : null;
            if ($userId && $eval->tim) {
                $timSaya = $eval->tim->where('user_id', $userId)->first();
                if ($timSaya) {
                    $peranSaya = $timSaya->peran ?? $timSaya->pivot->peran ?? 'Anggota Tim';
                }
            }

            // Penentuan Status untuk UI
            $statusUI = 'SIAP DIHITUNG';
            if (in_array($eval->status, ['Selesai Evaluasi', 'Menunggu Verifikasi Hasil', 'Selesai'])) {
                $statusUI = 'HASIL TERVALIDASI';
            }

            return [
                'id' => $eval->id,
                'proyek' => $namaProyek,
                'noSurat' => $eval->nomor_surat ?? '-',
                'lokasi' => $lokasi,
                'periode' => $eval->periode_evaluasi,
                'peran' => $peranSaya,
                'status' => $statusUI,
                'status_asli' => $eval->status
            ];
        });

        return response()->json([
            'message' => 'Daftar penugasan siap hitung',
            'data' => $mappedData
        ]);
    }

    /**
     * PUT /api/penugasan-evaluasi/{id}/faktual
     * Menyimpan data evaluasi lapangan (tumbuh, tinggi, kondisi) ke masing-masing Petak Ukur
     */
    public function saveFaktual(Request $request, $id): JsonResponse
    {
        $request->validate([
            'petak_ukurs' => 'required|array',
        ]);

        foreach ($request->petak_ukurs as $puData) {
            $pu = \App\Models\PetakUkur::find($puData['id']);
            if ($pu) {
                // Menghitung persentase tumbuh
                $rencana = $pu->dataTanamans()->sum('jumlah') ?: 1; // Cegah division by zero
                $persentase = ($puData['tumbuh'] / $rencana) * 100;

                $pu->update([
                    'eval_bibit_tumbuh' => $puData['tumbuh'],
                    'eval_tinggi_rata' => $puData['tinggi'],
                    'eval_keterangan' => $puData['kondisiLahan'],
                    'eval_koordinat' => $puData['koordinat'],
                    'eval_persentase_tumbuh' => min($persentase, 100),
                    'eval_at' => now()
                ]);
            }
        }

        return response()->json(['message' => 'Data faktual lapangan berhasil disimpan']);
    }

    /**
     * PUT /api/penugasan-evaluasi/{id}/kalkulasi
     */
    public function kalkulasiEvaluasi(Request $request, $id): JsonResponse
    {
        $evaluasi = Evaluasi::findOrFail($id);
        $evaluasi->status = 'Selesai Evaluasi';
        
        // PERBAIKAN: Simpan persentase_tumbuh jika ada
        if ($request->has('persentase_tumbuh')) {
            $evaluasi->persentase_tumbuh = $request->persentase_tumbuh;

            // Jika persentase tumbuh >= 75%, program di modul pelaksanaan monitoring berubah statusnya menjadi 'Monitoring Selesai'
            if ($request->persentase_tumbuh >= 75) {
                \App\Models\Penugasan::where('penugasanable_type', $evaluasi->evaluable_type)
                    ->where('penugasanable_id', $evaluasi->evaluable_id)
                    ->where('jenis_kegiatan', 'Monitoring')
                    ->update(['status' => 'Monitoring Selesai']);
            }
        }
        
        $evaluasi->save();

        return response()->json(['message' => 'Evaluasi berhasil dikalkulasi', 'data' => $evaluasi]);
    }

    /**
     * POST /api/penugasan-evaluasi/{id}/tindak-lanjut
     * Membuat penugasan baru berupa Arahan Tindak Lanjut untuk Penyuluh
     */
    public function submitTindakLanjut(Request $request, $id): JsonResponse
    {
        $evaluasi = Evaluasi::findOrFail($id);
        
        // 1. Ubah status evaluasi ini menjadi 'Tindak Lanjut'
        $evaluasi->status = 'Tindak Lanjut';
        $evaluasi->save();

        // 2. Cari ID penyuluh dari penugasan pelaksanaan sebelumnya
        $pelaksanaan = \App\Models\Penugasan::where('penugasanable_type', $evaluasi->evaluable_type)
            ->where('penugasanable_id', $evaluasi->evaluable_id)
            ->where('jenis_kegiatan', 'Pelaksanaan Penanaman')
            ->first();

        // 3. Buat penugasan baru di modul Pelaksanaan & Monitoring dengan jenis_kegiatan 'Tindak Lanjut'
        $penugasanTL = \App\Models\Penugasan::create([
            'penyuluh_id' => $pelaksanaan ? $pelaksanaan->penyuluh_id : null,
            'jenis_kegiatan' => 'Tindak Lanjut',
            'penugasanable_type' => $evaluasi->evaluable_type,
            'penugasanable_id' => $evaluasi->evaluable_id,
            'status' => 'Ditugaskan',
            'tanggal_penugasan' => now(),
            'batas_waktu' => $request->batas_waktu,
            'prioritas' => $request->prioritas,
            'arahan' => "TINDAK LANJUT EVALUASI [{$request->jenis_tindak_lanjut}]: \n" . $request->arahan,
        ]);

        return response()->json([
            'message' => 'Arahan Tindak Lanjut berhasil dibuat dan dikirim ke Penyuluh.',
            'data' => $penugasanTL
        ]);
    }

    /**
     * GET /api/penugasan-evaluasi-laporan-kabid
     * Mengambil daftar evaluasi yang sudah dihitung pada tahap perhitungan hasil evaluasi untuk Kabid
     */
    public function listLaporanKabid(Request $request): JsonResponse
    {
        $evaluasis = Evaluasi::with(['evaluable.kth', 'tim.user.pegawai'])
            ->whereNotNull('persentase_tumbuh')
            ->orWhereIn('status', [
                'Selesai Evaluasi', 
                'Menunggu Pengesahan', 
                'Menunggu Pengesahan KABID',
                'Menunggu Verifikasi Hasil', 
                'Tindak Lanjut', 
                'Disetujui', 
                'Disetujui KABID', 
                'Selesai'
            ])
            ->orderBy('updated_at', 'desc')
            ->get();

        $mappedData = $evaluasis->map(function ($eval) {
            $program = $eval->evaluable;
            $namaProyek = '-';
            $lokasi = '-';
            
            if ($eval->evaluable_type === 'App\\Models\\ProgramApbd') {
                $namaProyek = $program->nama_program ?? 'Program APBD';
                $lokasi = $program->lokasi ?? ($program->kth ? ($program->kth->desa_kelurahan . ', ' . $program->kth->kabupaten_kota) : '-');
            } elseif ($eval->evaluable_type === 'App\\Models\\ProgramCsr') {
                $namaProyek = $program->nama_program ?? 'Program CSR';
                $lokasi = $program->lokasi ?? ($program->kth ? ($program->kth->desa_kelurahan . ', ' . $program->kth->kabupaten_kota) : '-');
            } elseif ($eval->evaluable_type === 'App\\Models\\DonationProgram') {
                $namaProyek = $program->name ?? 'Program Donasi';
                $lokasi = $program->location ?? '-';
            }

            // Tim Penilai / Tim Penyusun
            $timString = '-';
            if ($eval->tim && $eval->tim->count() > 0) {
                $ketua = $eval->tim->firstWhere('peran', 'Ketua Tim');
                $first = $ketua ?: $eval->tim->first();
                $name = $first->user ? ($first->user->username ?? $first->user->name) : 'Staff PDAS';
                $timString = $eval->tim->count() > 1 ? "{$name} Dkk" : $name;
            }

            // Status UI untuk KABID
            $isDisetujui = in_array($eval->status, ['Disetujui', 'Disetujui KABID', 'Selesai']);
            $statusUI = $isDisetujui ? 'DISETUJUI' : 'MENUNGGU PENGESAHAN';

            return [
                'id' => $eval->id,
                'proyek' => $namaProyek,
                'lokasi' => $lokasi,
                'periode' => $eval->periode_evaluasi ?? 'Penanaman Awal (P0)',
                'tim' => $timString,
                'status' => $statusUI,
                'status_asli' => $eval->status,
                'persentase_tumbuh' => $eval->persentase_tumbuh,
                'tanggal_validasi' => $eval->updated_at ? $eval->updated_at->isoFormat('D MMMM Y') : '-',
            ];
        });

        return response()->json([
            'message' => 'Daftar laporan evaluasi untuk Kabid',
            'data' => $mappedData
        ]);
    }

    /**
     * PUT /api/penugasan-evaluasi/{id}/sahkan
     * Pengesahan laporan evaluasi oleh Kepala Bidang PDAS
     */
    public function sahkanLaporan(Request $request, $id): JsonResponse
    {
        $evaluasi = Evaluasi::findOrFail($id);
        $evaluasi->status = 'Disetujui KABID';
        if ($request->filled('catatan')) {
            $evaluasi->catatan = $request->catatan;
        }
        $evaluasi->save();

        return response()->json([
            'message' => 'Laporan evaluasi berhasil disahkan!',
            'data' => $evaluasi
        ]);
    }

    /**
     * PUT /api/penugasan-evaluasi/{id}/revisi
     * Mengembalikan laporan evaluasi ke tim penilai untuk revisi
     */
    public function revisiLaporan(Request $request, $id): JsonResponse
    {
        $evaluasi = Evaluasi::findOrFail($id);
        $evaluasi->status = 'Perlu Revisi';
        if ($request->filled('catatan')) {
            $evaluasi->catatan = $request->catatan;
        }
        $evaluasi->save();

        return response()->json([
            'message' => 'Laporan evaluasi dikembalikan untuk revisi',
            'data' => $evaluasi
        ]);
    }
}
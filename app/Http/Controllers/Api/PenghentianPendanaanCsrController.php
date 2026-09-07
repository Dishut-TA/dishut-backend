<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Csr;
use App\Models\Evaluasi;
use App\Models\Penugasan;
use App\Models\ProgramCsr;
use App\Models\TransaksiCsr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Modul Investasi CSR - Penghentian Pendanaan (PRD Feature 7).
 *
 * Pihak pendana CSR boleh menghentikan pendanaan apabila hasil evaluasi
 * menunjukkan persentase tumbuh di bawah 75%. Keputusan tersebut merambat
 * ke Modul Pelaksanaan & Monitoring: seluruh penugasan yang masih berjalan
 * ikut berstatus 'Dihentikan'.
 *
 * Seluruh endpoint di sini wajib terautentikasi (auth:sanctum). Kepemilikan
 * program ditentukan lewat tabel transaksi_csrs: user -> csrs -> transaksi_csrs.
 */
class PenghentianPendanaanCsrController extends Controller
{
    /**
     * Status penugasan yang sudah final, jadi tidak perlu ikut dihentikan.
     */
    private const STATUS_PENUGASAN_FINAL = [
        'Selesai',
        'Monitoring Selesai',
        'Dihentikan',
    ];

    /**
     * GET /api/program-csrs/saya
     *
     * Daftar program CSR yang didanai oleh akun mitra CSR yang sedang login.
     * Dipakai halaman Monitoring Proyek agar mitra hanya melihat programnya sendiri.
     */
    public function programSaya(Request $request): JsonResponse
    {
        $csr = $this->csrMilikUser($request);

        if (! $csr) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akun Anda tidak terdaftar sebagai mitra CSR.',
            ], 403);
        }

        $programs = ProgramCsr::with(['kth', 'transaksiCsrs'])
            ->whereHas('transaksiCsrs', function ($query) use ($csr) {
                $query->where('csr_id', $csr->id);
            })
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar program CSR yang Anda danai',
            'data' => $programs,
        ]);
    }

    /**
     * GET /api/program-csrs/{id}/hasil-evaluasi
     *
     * Menampilkan hasil evaluasi terakhir sebuah program CSR beserta
     * penilaian apakah pendanaannya boleh dihentikan.
     *
     * Mitra CSR hanya boleh melihat program yang ia danai. User internal
     * (Staff/Kabid PDAS, yang tidak punya profil mitra CSR) tetap boleh membaca.
     */
    public function hasilEvaluasi(Request $request, string $id): JsonResponse
    {
        $program = ProgramCsr::with('kth')->findOrFail($id);

        $tolakan = $this->tolakBukanPendana($request, $program, true);
        if ($tolakan) {
            return $tolakan;
        }

        $evaluasi = $program->evaluasiFinalTerakhir();
        $ambang = ProgramCsr::AMBANG_BATAS_TUMBUH;

        $persentase = $evaluasi ? (float) $evaluasi->persentase_tumbuh : null;
        $dibawahAmbang = $persentase !== null && $persentase < $ambang;
        $adalahPendana = $this->csrMilikUser($request) !== null;

        return response()->json([
            'status' => 'success',
            'message' => 'Hasil evaluasi program CSR',
            'data' => [
                'program_csr_id' => $program->id,
                'nama_program' => $program->nama_program,
                'status_program' => $program->status,
                'ambang_batas_tumbuh' => $ambang,
                'persentase_tumbuh' => $persentase,
                'di_bawah_ambang_batas' => $dibawahAmbang,
                'boleh_dihentikan' => $dibawahAmbang && $adalahPendana && ! $program->sudahDihentikan(),
                'alasan_tidak_boleh' => $this->alasanTidakBoleh($program, $evaluasi, $persentase, $ambang, $adalahPendana),
                'evaluasi' => $evaluasi,
                'penghentian' => $program->sudahDihentikan() ? [
                    'alasan' => $program->alasan_penghentian,
                    'dihentikan_at' => $program->dihentikan_at,
                    'dihentikan_by' => $program->dihentikan_by,
                    'persentase_tumbuh_terakhir' => $program->persentase_tumbuh_terakhir,
                ] : null,
            ],
        ]);
    }

    /**
     * POST /api/program-csrs/{id}/hentikan-pendanaan
     *
     * Menghentikan pendanaan CSR dan merambatkan status 'Dihentikan' ke
     * transaksi yang belum dibayar, evaluasi berjalan, dan penugasan
     * pelaksanaan/monitoring yang belum selesai.
     *
     * Hanya mitra CSR yang tercatat mendanai program ini yang boleh memanggilnya.
     */
    public function hentikanPendanaan(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'alasan' => 'required|string|min:5|max:1000',
        ]);

        $program = ProgramCsr::findOrFail($id);
        $ambang = ProgramCsr::AMBANG_BATAS_TUMBUH;

        $tolakan = $this->tolakBukanPendana($request, $program, false);
        if ($tolakan) {
            return $tolakan;
        }

        if ($program->sudahDihentikan()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pendanaan program ini sudah dihentikan sebelumnya.',
            ], 409);
        }

        $evaluasi = $program->evaluasiFinalTerakhir();

        if (! $evaluasi) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pendanaan belum dapat dihentikan karena program ini belum memiliki hasil evaluasi yang final.',
            ], 422);
        }

        $persentase = (float) $evaluasi->persentase_tumbuh;

        if ($persentase >= $ambang) {
            return response()->json([
                'status' => 'error',
                'message' => "Pendanaan hanya dapat dihentikan bila persentase tumbuh di bawah {$ambang}%. Hasil evaluasi terakhir: {$persentase}%.",
            ], 422);
        }

        $actorId = $request->user()->id;

        DB::transaction(function () use ($program, $evaluasi, $persentase, $request, $actorId) {
            $program->update([
                'status' => 'Dihentikan',
                'dihentikan_evaluasi_id' => $evaluasi->id,
                'persentase_tumbuh_terakhir' => $persentase,
                'alasan_penghentian' => $request->alasan,
                'dihentikan_by' => $actorId,
                'dihentikan_at' => now(),
            ]);

            TransaksiCsr::where('program_csr_id', $program->id)
                ->where('status', 'Menunggu Pembayaran')
                ->update(['status' => 'Dihentikan']);

            Evaluasi::where('evaluable_type', ProgramCsr::class)
                ->where('evaluable_id', $program->id)
                ->whereNotIn('status', ['Selesai', 'Disetujui KABID', 'Dihentikan'])
                ->update(['status' => 'Dihentikan']);

            Penugasan::where('penugasanable_type', ProgramCsr::class)
                ->where('penugasanable_id', $program->id)
                ->whereNotIn('status', self::STATUS_PENUGASAN_FINAL)
                ->each(function (Penugasan $penugasan) use ($request) {
                    $penugasan->update([
                        'status' => 'Dihentikan',
                        'arahan' => trim(($penugasan->arahan ?? '') . "\n[Dihentikan - Pendanaan CSR] " . $request->alasan),
                    ]);
                });
        });

        $penugasanDihentikan = Penugasan::where('penugasanable_type', ProgramCsr::class)
            ->where('penugasanable_id', $program->id)
            ->where('status', 'Dihentikan')
            ->count();

        return response()->json([
            'status' => 'success',
            'message' => 'Pendanaan CSR dihentikan. Status monitoring program ikut diperbarui menjadi Dihentikan.',
            'data' => [
                'program' => $program->fresh(['kth', 'transaksiCsrs']),
                'penugasan_dihentikan' => $penugasanDihentikan,
                'evaluasi_dasar' => $evaluasi->fresh(),
            ],
        ]);
    }

    /**
     * Profil mitra CSR milik user yang sedang login, null bila user internal.
     */
    private function csrMilikUser(Request $request): ?Csr
    {
        $user = $request->user();

        return $user ? $user->csr : null;
    }

    /**
     * Menolak user yang bukan pendana program ini.
     *
     * Kepemilikan dibaca dari transaksi_csrs: ada baris yang menghubungkan
     * csr_id milik user dengan program_csr_id yang diminta.
     *
     * @param bool $izinkanInternal true untuk endpoint baca, sehingga user
     *                              internal (tanpa profil mitra CSR) tetap lolos.
     */
    private function tolakBukanPendana(Request $request, ProgramCsr $program, bool $izinkanInternal): ?JsonResponse
    {
        $csr = $this->csrMilikUser($request);

        if (! $csr) {
            if ($izinkanInternal) {
                return null;
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Hanya mitra CSR pendana program ini yang dapat menghentikan pendanaan.',
            ], 403);
        }

        $mendanai = TransaksiCsr::where('program_csr_id', $program->id)
            ->where('csr_id', $csr->id)
            ->exists();

        if (! $mendanai) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak tercatat sebagai pendana program CSR ini.',
            ], 403);
        }

        return null;
    }

    /**
     * Pesan yang menjelaskan kenapa tombol "Hentikan Pendanaan" belum aktif.
     */
    private function alasanTidakBoleh(
        ProgramCsr $program,
        ?Evaluasi $evaluasi,
        ?float $persentase,
        int $ambang,
        bool $adalahPendana
    ): ?string {
        if ($program->sudahDihentikan()) {
            return 'Pendanaan program ini sudah dihentikan.';
        }

        if (! $evaluasi) {
            return 'Program ini belum memiliki hasil evaluasi yang final.';
        }

        if ($persentase >= $ambang) {
            return "Persentase tumbuh {$persentase}% masih memenuhi ambang batas {$ambang}%.";
        }

        if (! $adalahPendana) {
            return 'Penghentian pendanaan hanya dapat dilakukan oleh mitra CSR pendana program ini.';
        }

        return null;
    }
}

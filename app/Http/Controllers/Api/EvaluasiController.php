<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Penugasan;
use App\Models\DonationProgram;
use App\Models\ProgramApbd;
use App\Models\ProgramCsr;
use App\Models\PetakUkur;

class EvaluasiController extends Controller
{
    /**
     * Get monitoring dashboard stats for evaluation
     */
    public function dashboardStats(): JsonResponse
    {
        $penugasans = Penugasan::where('jenis_kegiatan', 'Monitoring')
            ->whereIn('status', ['Menunggu Evaluasi', 'Tindak Lanjut', 'Monitoring Selesai'])
            ->get();

        $totalTargetBibit = 0;
        $totalBibitHidup = 0;
        $totalPersentaseTumbuh = 0;
        $count = 0;
        $puGagal = 0;

        foreach ($penugasans as $p) {
            // Find the original Pelaksanaan Penanaman penugasan to get the PU
            $originalPenugasan = Penugasan::where('penugasanable_type', $p->penugasanable_type)
                ->where('penugasanable_id', $p->penugasanable_id)
                ->where('jenis_kegiatan', 'Pelaksanaan Penanaman')
                ->with('petakUkurs.dataTanamans')
                ->first();

            if (!$originalPenugasan) {
                continue;
            }

            // Determine target bibit from program
            $targetBibit = 0;
            $program = $p->penugasanable;
            if ($p->penugasanable_type === 'App\\Models\\DonationProgram' && $program) {
                $targetBibit = $program->target_amount ?? 0;
            } elseif (in_array($p->penugasanable_type, ['App\\Models\\ProgramApbd', 'App\\Models\\ProgramCsr']) && $program) {
                $targetBibit = $program->target_bibit ?? ($program->jumlah_bibit ?? 0);
            }

            if ($targetBibit <= 0) continue;

            $totalTargetBibit += $targetBibit;
            
            $hidup = 0;
            $targetPerPu = count($originalPenugasan->petakUkurs) > 0 ? $targetBibit / count($originalPenugasan->petakUkurs) : 0;

            foreach ($originalPenugasan->petakUkurs as $pu) {
                $puHidup = 0;
                foreach ($pu->dataTanamans as $t) {
                    $kondisi = strtolower($t->kondisi_tanaman);
                    if (str_contains($kondisi, 'hidup') || str_contains($kondisi, 'sehat') || str_contains($kondisi, 'baik')) {
                        $puHidup += $t->jumlah;
                    }
                }
                
                // If PU survival rate is < 75%, it's considered PU Gagal
                if ($targetPerPu > 0 && ($puHidup / $targetPerPu) < 0.75) {
                    $puGagal++;
                }

                $hidup += $puHidup;
            }

            $totalBibitHidup += $hidup;
            $totalPersentaseTumbuh += ($hidup / $targetBibit) * 100;
            $count++;
        }

        $rataRataTumbuh = $count > 0 ? $totalPersentaseTumbuh / $count : 0;

        return response()->json([
            'message' => 'Dashboard Stats Evaluasi',
            'data' => [
                'total_target_bibit' => $totalTargetBibit,
                'total_bibit_hidup' => $totalBibitHidup,
                'rata_rata_persentase_tumbuh' => round($rataRataTumbuh, 2),
                'pu_gagal' => $puGagal
            ]
        ]);
    }

    /**
     * Get list of evaluations
     */
    public function index(Request $request): JsonResponse
    {
        $penugasans = Penugasan::with(['penyuluh.kth', 'penugasanable'])
            ->where('jenis_kegiatan', 'Monitoring')
            ->whereIn('status', ['Menunggu Evaluasi', 'Tindak Lanjut', 'Monitoring Selesai'])
            ->get();

        return response()->json([
            'message' => 'Daftar Evaluasi Penanaman',
            'data' => $penugasans
        ]);
    }

    /**
     * Get evaluation details by id
     */
    public function show($id): JsonResponse
    {
        $penugasan = Penugasan::with(['penyuluh.kth', 'penugasanable'])->find($id);

        if (!$penugasan) {
            return response()->json(['message' => 'Penugasan tidak ditemukan'], 404);
        }

        // Get Petak Ukur from original Penugasan
        $originalPenugasan = Penugasan::where('penugasanable_type', $penugasan->penugasanable_type)
            ->where('penugasanable_id', $penugasan->penugasanable_id)
            ->where('jenis_kegiatan', 'Pelaksanaan Penanaman')
            ->with('petakUkurs.dataTanamans')
            ->first();

        if ($originalPenugasan) {
            $penugasan->setRelation('petakUkurs', $originalPenugasan->petakUkurs);
        } else {
            $penugasan->setRelation('petakUkurs', collect([]));
        }

        // Target bibit
        $targetBibit = 0;
        $program = $penugasan->penugasanable;
        if ($penugasan->penugasanable_type === 'App\\Models\\DonationProgram' && $program) {
            $targetBibit = $program->target_amount ?? 0;
        } elseif (in_array($penugasan->penugasanable_type, ['App\\Models\\ProgramApbd', 'App\\Models\\ProgramCsr']) && $program) {
            $targetBibit = $program->target_bibit ?? ($program->jumlah_bibit ?? 0);
        }
        
        $penugasan->target_bibit_seharusnya = $targetBibit;

        return response()->json([
            'message' => 'Detail Evaluasi',
            'data' => $penugasan
        ]);
    }

    /**
     * Simpan hasil evaluasi lapangan per Petak Ukur dan tandai penugasan Selesai.
     * Menerima: petaks[] = [{ petak_ukur_id, bibit_tumbuh, tinggi_rata, koordinat, keterangan, foto (file) }]
     */
    public function submit(Request $request, $id): JsonResponse
    {
        $penugasan = Penugasan::find($id);

        if (!$penugasan) {
            return response()->json(['message' => 'Penugasan tidak ditemukan'], 404);
        }

        $request->validate([
            'petaks' => 'required|array|min:1',
            'petaks.*.petak_ukur_id' => 'required|exists:petak_ukurs,id',
            'petaks.*.bibit_tumbuh' => 'required|integer|min:0',
            'petaks.*.tinggi_rata' => 'nullable|numeric',
            'petaks.*.koordinat' => 'required|string',
            'petaks.*.keterangan' => 'nullable|string',
        ]);

        $totalPersentase = 0;
        $jumlahPu = 0;

        foreach ($request->petaks as $index => $petakInput) {
            $petak = PetakUkur::find($petakInput['petak_ukur_id']);
            if (!$petak) {
                continue;
            }

            $rencana = $petak->dataTanamans()->count();
            $tumbuh = (int) $petakInput['bibit_tumbuh'];
            $persentase = $rencana > 0 ? round(($tumbuh / $rencana) * 100, 2) : 0;

            $fotoPath = $petak->eval_foto;
            $fotoFile = $request->file("petaks.$index.foto");
            if ($fotoFile) {
                $fotoPath = $fotoFile->store('evaluasi_petak', 'public');
            }

            $petak->update([
                'eval_bibit_tumbuh' => $tumbuh,
                'eval_persentase_tumbuh' => $persentase,
                'eval_tinggi_rata' => $petakInput['tinggi_rata'] ?? null,
                'eval_koordinat' => $petakInput['koordinat'],
                'eval_keterangan' => $petakInput['keterangan'] ?? null,
                'eval_foto' => $fotoPath,
                'eval_at' => now(),
            ]);

            $totalPersentase += $persentase;
            $jumlahPu++;
        }

        $rataPersentase = $jumlahPu > 0 ? round($totalPersentase / $jumlahPu, 2) : 0;

        $penugasan->update([
            'status' => 'Monitoring Selesai',
            'persentase_tumbuh' => $rataPersentase,
        ]);

        return response()->json([
            'message' => 'Evaluasi lapangan berhasil disimpan, status diperbarui menjadi Selesai',
            'data' => $penugasan->fresh(['petakUkurs'])
        ]);
    }

    /**
     * Versi simpel: langsung tandai penugasan Monitoring sebagai selesai
     * tanpa perlu mengisi form evaluasi per Petak Ukur satu-satu.
     */
    public function selesaikan($id): JsonResponse
    {
        $penugasan = Penugasan::find($id);

        if (!$penugasan) {
            return response()->json(['message' => 'Penugasan tidak ditemukan'], 404);
        }

        $penugasan->update([
            'status' => 'Monitoring Selesai'
        ]);

        return response()->json([
            'message' => 'Penugasan berhasil ditandai selesai',
            'data' => $penugasan
        ]);
    }
}
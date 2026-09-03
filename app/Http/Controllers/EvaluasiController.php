<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Evaluasi;
use App\Models\EvaluasiTim;
use App\Models\DonationProgram;
use App\Models\ProgramApbd;
use App\Models\ProgramCsr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EvaluasiController extends Controller
{
    public function index()
    {
        $evaluasis = Evaluasi::with(['evaluable', 'tim.user'])->get();
        return response()->json(['data' => $evaluasis]);
    }

    public function getProgramsReady()
    {
        // Programs that have finished monitoring. For this task, we can just return all running/finished programs.
        // Let's get DonationPrograms, ProgramApbds, ProgramCsrs that are Berjalan or Selesai.
        
        $donations = DonationProgram::whereIn('status', ['Berjalan', 'Selesai'])->get()->map(function($p) {
            return [
                'id_program' => $p->id,
                'nama_program' => $p->program_name,
                'lokasi' => $p->location,
                'jenis_program' => 'Donasi',
                'target_bibit' => $p->target_amount
            ];
        });

        $apbds = ProgramApbd::whereIn('status_pelaksanaan', ['Sedang Berjalan', 'Selesai'])->get()->map(function($p) {
            return [
                'id_program' => $p->id,
                'nama_program' => $p->nama_program,
                'lokasi' => 'Lahan Prioritas APBD',
                'jenis_program' => 'APBD',
                'target_bibit' => $p->target_bibit
            ];
        });

        $csrs = ProgramCsr::whereIn('status_pelaksanaan', ['Sedang Berjalan', 'Selesai'])->get()->map(function($p) {
            return [
                'id_program' => $p->id,
                'nama_program' => $p->nama_program,
                'lokasi' => 'Lahan Prioritas CSR',
                'jenis_program' => 'CSR',
                'target_bibit' => $p->target_bibit
            ];
        });

        return response()->json(['payload' => $donations->concat($apbds)->concat($csrs)]);
    }

    public function storePenugasan(Request $request)
    {
        $request->validate([
            'file_surat_tugas' => 'required|file|mimes:pdf',
            'nomor_surat' => 'required|string',
            'tanggal_surat' => 'required|date',
            'id_program' => 'required|integer',
            'jenis_program' => 'required|string',
            'periode_evaluasi' => 'required|string',
            'tanggal_pelaksanaan_mulai' => 'required|date',
            'tanggal_pelaksanaan_selesai' => 'required|date',
            'tim_penilai' => 'required|json'
        ]);

        DB::beginTransaction();
        try {
            $path = $request->file('file_surat_tugas')->store('evaluasi_surat', 'public');

            // Resolve polymorphic relation
            $evaluableType = null;
            if ($request->jenis_program === 'Donasi') {
                $evaluableType = DonationProgram::class;
            } elseif ($request->jenis_program === 'APBD') {
                $evaluableType = ProgramApbd::class;
            } elseif ($request->jenis_program === 'CSR') {
                $evaluableType = ProgramCsr::class;
            }

            $evaluasi = Evaluasi::create([
                'nomor_surat' => $request->nomor_surat,
                'tanggal_surat' => $request->tanggal_surat,
                'evaluable_id' => $request->id_program,
                'evaluable_type' => $evaluableType,
                'periode_evaluasi' => $request->periode_evaluasi,
                'tanggal_mulai' => $request->tanggal_pelaksanaan_mulai,
                'tanggal_selesai' => $request->tanggal_pelaksanaan_selesai,
                'status' => 'Menunggu Pelaksanaan',
                'file_surat_tugas' => $path
            ]);

            $timPenilai = json_decode($request->tim_penilai, true);
            foreach ($timPenilai as $tim) {
                if(!empty($tim['id_user'])){
                    EvaluasiTim::create([
                        'evaluasi_id' => $evaluasi->id,
                        'user_id' => $tim['id_user'],
                        'peran' => $tim['peran'] ?? 'Anggota Tim'
                    ]);
                }
            }

            DB::commit();
            return response()->json(['message' => 'Penugasan berhasil diterbitkan', 'payload' => $evaluasi], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menerbitkan penugasan: ' . $e->getMessage()], 500);
        }
    }

    public function submitLaporan(Request $request, $id)
    {
        $evaluasi = Evaluasi::findOrFail($id);

        $request->validate([
            'persentase_tumbuh' => 'required|numeric|min:0|max:100',
            'file_laporan' => 'required|file|mimes:pdf',
            'catatan' => 'nullable|string'
        ]);

        $path = $request->file('file_laporan')->store('evaluasi_laporan', 'public');
        
        $evaluasi->persentase_tumbuh = $request->persentase_tumbuh;
        $evaluasi->file_laporan = $path;
        $evaluasi->catatan = $request->catatan;

        $program = $evaluasi->evaluable;
        
        if ($request->persentase_tumbuh >= 75) {
            $evaluasi->status = 'Selesai';
            // update status program to lanjut monitoring / selesai
        } else {
            // < 75%
            if ($evaluasi->evaluable_type == ProgramApbd::class) {
                $evaluasi->status = 'Tindak Lanjut'; // APBD langsung tindak lanjut
            } elseif ($evaluasi->evaluable_type == ProgramCsr::class) {
                // CSR butuh konfirmasi
                $evaluasi->status = 'Menunggu Keputusan Mitra CSR';
            } else {
                $evaluasi->status = 'Tindak Lanjut'; // Donasi
            }
        }

        $evaluasi->save();

        return response()->json(['message' => 'Laporan berhasil disubmit', 'data' => $evaluasi]);
    }
}

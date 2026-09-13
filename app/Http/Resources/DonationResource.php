<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Penugasan;
use App\Models\DokumentasiPenugasan;

class DonationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'donation_program_id' => $this->donation_program_id,
            'donor_id' => $this->donor_id,
            'transaction_id' => $this->transaction_id,
            'seed_details' => $this->seed_details,
            'seed_status' => $this->seed_status,
            'receipt_path' => $this->receipt_path,
            'certificate_path' => $this->certificate_path,
            'bast_path' => $this->bast_path,
            'bast_url' => $this->bast_path ? asset('storage/' . $this->bast_path) : null,
            'proof_path' => $this->proof_path,
            'proof_url' => $this->proof_path ? asset('storage/' . $this->proof_path) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'donor' => new DonorResource($this->whenLoaded('donor')),
            'donation_program' => new DonationProgramResource($this->whenLoaded('donationProgram')),
            'transaction' => new TransactionResource($this->whenLoaded('transaction')),
            'dokumentasi_penanaman' => $this->getDokumentasiPenanaman(),
        ];
    }

    /**
     * Ambil foto dokumentasi dari penugasan Pelaksanaan Penanaman program donasi ini.
     * Prioritas: "Proses Penanaman" dulu. Jika tidak ada, ambil semua jenis dokumentasi.
     */
    private function getDokumentasiPenanaman(): array
    {
        if (!$this->donation_program_id) {
            return [];
        }

        $penugasan = Penugasan::where('penugasanable_type', 'App\Models\DonationProgram')
            ->where('penugasanable_id', $this->donation_program_id)
            ->where('jenis_kegiatan', 'Pelaksanaan Penanaman')
            ->first();

        if (!$penugasan) {
            return [];
        }

        $dokProses = DokumentasiPenugasan::where('penugasan_id', $penugasan->id)
            ->where('jenis_dokumentasi', 'Proses Penanaman')
            ->get();

        $dokList = $dokProses->count() > 0
            ? $dokProses
            : DokumentasiPenugasan::where('penugasan_id', $penugasan->id)->get();

        return $dokList->map(function ($dok) {
            return [
                'id' => $dok->id,
                'jenis_dokumentasi' => $dok->jenis_dokumentasi,
                'keterangan' => $dok->keterangan,
                'file_path' => $dok->file_path,
                'url' => $dok->file_path ? asset('storage/' . $dok->file_path) : null,
                'created_at' => $dok->created_at,
            ];
        })->values()->toArray();
    }
}
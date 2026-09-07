<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramCsr extends Model
{
    /**
     * Ambang batas persentase tumbuh. Di bawah nilai ini pendanaan CSR
     * boleh dihentikan oleh pihak pendana (PRD Feature 7).
     */
    public const AMBANG_BATAS_TUMBUH = 75;

    /**
     * Status evaluasi yang sudah dianggap final, sehingga persentase
     * tumbuhnya boleh dipakai sebagai dasar penghentian pendanaan.
     */
    public const STATUS_EVALUASI_FINAL = [
        'Selesai Evaluasi',
        'Tindak Lanjut',
        'Disetujui KABID',
    ];

    protected $fillable = [
        'kth_id',
        'analysis_result_zone_id',
        'nama_program',
        'deskripsi_rencana',
        'lokasi',
        'anggaran',
        'target_luas_lahan',
        'jumlah_bibit',
        'jenis_tanaman',        
        'proposal_file_path',   
        'tanggapan_perusahaan',
        'status',
        'catatan_staff',           
        'rekomendasi_mitra',       
        'rekomendasi_intervensi',
        'dihentikan_evaluasi_id',
        'persentase_tumbuh_terakhir',
        'alasan_penghentian',
        'dihentikan_by',
        'dihentikan_at',
    ];

    protected $casts = [
        'persentase_tumbuh_terakhir' => 'float',
        'dihentikan_at' => 'datetime',
    ];

    public function kth()
    {
        return $this->belongsTo(Kth::class);
    }

    public function analysisResultZone()
    {
        return $this->belongsTo(AnalysisResultZone::class, 'analysis_result_zone_id');
    }

    public function transaksiCsrs()
    {
        return $this->hasMany(TransaksiCsr::class);
    }

    public function laporanDanas()
    {
        return $this->morphMany(LaporanDana::class, 'program');
    }

    public function laporanProyeks()
    {
        return $this->morphMany(LaporanProyek::class, 'program');
    }

    public function dokumens()
    {
        return $this->morphMany(Dokumen::class, 'documentable');
    }

    public function penugasans()
    {
        return $this->morphMany(Penugasan::class, 'penugasanable');
    }

    public function evaluasis()
    {
        return $this->morphMany(Evaluasi::class, 'evaluable');
    }

    public function dihentikanOleh()
    {
        return $this->belongsTo(User::class, 'dihentikan_by');
    }

    /**
     * Evaluasi terakhir yang sudah final dan punya persentase tumbuh.
     * Ini yang jadi dasar keputusan penghentian pendanaan.
     */
    public function evaluasiFinalTerakhir(): ?Evaluasi
    {
        return $this->evaluasis()
            ->whereNotNull('persentase_tumbuh')
            ->whereIn('status', self::STATUS_EVALUASI_FINAL)
            ->latest('id')
            ->first();
    }

    public function sudahDihentikan(): bool
    {
        return $this->status === 'Dihentikan';
    }
}

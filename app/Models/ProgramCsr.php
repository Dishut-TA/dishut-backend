<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramCsr extends Model
{
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
}

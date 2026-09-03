<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramApbd extends Model
{
    protected $fillable = [
        'kth_id',
        'analysis_result_zone_id',
        'nama_program',
        'deskripsi_rencana',
        'anggaran',
        'jumlah_bibit',
        'target_luas_lahan',
        'pilihan_intervensi',
        'tanggal_mulai',
        'tanggal_selesai',
        'status',
    ];

    public function kth()
    {
        return $this->belongsTo(Kth::class);
    }

    public function analysisResultZone()
    {
        return $this->belongsTo(AnalysisResultZone::class, 'analysis_result_zone_id');
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

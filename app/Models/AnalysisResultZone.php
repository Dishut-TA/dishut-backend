<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisResultZone extends Model
{
    protected $fillable = [
        'result_id',
        'zone_id',
        'provinsi',
        'kabupaten',
        'kecamatan',
        'desa',
        'skor_cpi',
        'status_lahan_kritis',
        'luas_ha',
        'score_landcover',
        'score_rainfall',
        'score_soil',
        'score_slope',
        'slope_percent',
        'alasan_skor',
        'riwayat_intervensi',
        'rekomendasi_intervensi',
        'cdk',
        'nama_kelompok',
        'ketua_kelompok',
        'status_validasi_penyuluh',
        'status_kelayakan',
        'panjang_pu',
        'lebar_pu',
        'jumlah_pu',
    ];

    protected $casts = [
        'skor_cpi'       => 'float',
        'luas_ha'        => 'float',
        'score_landcover' => 'float',
        'score_rainfall' => 'float',
        'score_soil'     => 'float',
        'score_slope'    => 'float',
        'slope_percent'  => 'float',
    ];

    /**
     * Relasi ke result induk.
     */
    public function result(): BelongsTo
    {
        return $this->belongsTo(AnalysisResult::class, 'result_id');
    }

    /**
     * Relasi ke data validasi lapangan penyuluh.
     */
    public function fieldValidations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FieldValidation::class, 'zone_id');
    }
}

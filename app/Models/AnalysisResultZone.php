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
        'plant_recommendation_rule_id',
        'rekomendasi_tanaman',
        'rekomendasi_tanaman_alasan',
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
        'skor_cpi' => 'float',
        'luas_ha' => 'float',
        'score_landcover' => 'float',
        'score_rainfall' => 'float',
        'score_soil' => 'float',
        'score_slope' => 'float',
        'slope_percent' => 'float',
        'rekomendasi_tanaman' => 'array',
    ];

    protected $appends = ['titik_koordinat'];

    public function result(): BelongsTo
    {
        return $this->belongsTo(AnalysisResult::class, 'result_id');
    }

    public function plantRecommendationRule(): BelongsTo
    {
        return $this->belongsTo(PlantRecommendationRule::class, 'plant_recommendation_rule_id');
    }

    public function getTitikKoordinatAttribute(): ?string
    {
        if ($this->relationLoaded('village') && $this->village && $this->village->latitude && $this->village->longitude) {
            return $this->village->latitude . ', ' . $this->village->longitude;
        }
        return null;
    }

    public function fieldValidations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FieldValidation::class, 'zone_id');
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class, 'zone_id', 'id');
    }
}

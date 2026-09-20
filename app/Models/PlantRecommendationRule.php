<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlantRecommendationRule extends Model
{
    protected $fillable = [
        'code',
        'region_name',
        'region_keywords',
        'elevation_min',
        'elevation_max',
        'slope_min',
        'slope_max',
        'rainfall_min',
        'rainfall_max',
        'soil_keywords',
        'land_condition',
        'recommended_plants',
        'category',
        'rhl_function',
        'notes',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'region_keywords' => 'array',
        'soil_keywords' => 'array',
        'recommended_plants' => 'array',
        'elevation_min' => 'integer',
        'elevation_max' => 'integer',
        'slope_min' => 'float',
        'slope_max' => 'float',
        'rainfall_min' => 'integer',
        'rainfall_max' => 'integer',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function zones(): HasMany
    {
        return $this->hasMany(AnalysisResultZone::class, 'plant_recommendation_rule_id');
    }
}

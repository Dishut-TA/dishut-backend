<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DonationProgram extends Model
{
    protected $fillable = [
        'analysis_result_id',
        'kth_id',
        'name',
        'description',
        'location',
        'start_date', 
        'end_date',   
        'total_seeds_collected',
        'total_seeds_realized',
        'status',
        'bast_path',
        'image'
    ];

    public function analysisResult()
    {
        return $this->belongsTo(AnalysisResult::class);
    }

    public function analysisResultZone()
    {
        return $this->belongsTo(AnalysisResultZone::class, 'analysis_result_id');
    }

    public function kth()
    {
        return $this->belongsTo(Kth::class);
    }

    public function seeds()
    {
        return $this->belongsToMany(Seed::class, 'donation_program_seed');
    }

    public function donations()
    {
        return $this->hasMany(Donation::class);
    }

    public function plantedSeeds()
    {
        return $this->hasMany(PlantedSeed::class);
    }

    public function penugasans()
    {
        return $this->morphMany(Penugasan::class, 'penugasanable');
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }
}


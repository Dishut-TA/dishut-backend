<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PetakUkur extends Model
{
    use HasFactory;

    protected $table = 'petak_ukurs';

    protected $fillable = [
        'penugasan_id',
        'nama',
        'luas',
        'polygon_data',
        'status',
    ];

    protected $casts = [
        'polygon_data' => 'array',
        'luas' => 'decimal:2',
    ];

    public function penugasan()
    {
        return $this->belongsTo(Penugasan::class, 'penugasan_id');
    }

    public function dataTanamans()
    {
        return $this->hasMany(DataTanaman::class, 'petak_ukur_id');
    }
}

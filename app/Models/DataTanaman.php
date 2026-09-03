<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataTanaman extends Model
{
    protected $table = 'data_tanamans';
    protected $fillable = ['petak_ukur_id', 'seed_id', 'nama_tanaman', 'jumlah', 'kondisi_tanaman', 'keterangan', 'foto_url'];

    public function petakUkur()
    {
        return $this->belongsTo(PetakUkur::class);
    }

    public function seed()
    {
        return $this->belongsTo(Seed::class);
    }
}

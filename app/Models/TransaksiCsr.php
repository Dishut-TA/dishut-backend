<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransaksiCsr extends Model
{
    protected $fillable = [
        'csr_id',
        'program_csr_id',
        'tanggal_pendanaan',
        'nominal',
        'catatan',
        'status',
    ];

    public function csr()
    {
        return $this->belongsTo(Csr::class);
    }

    public function programCsr()
    {
        return $this->belongsTo(ProgramCsr::class);
    }

    public function dokumens()
    {
        return $this->morphMany(Dokumen::class, 'documentable');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dokumen extends Model
{
    protected $fillable = [
        'documentable_id',
        'documentable_type',
        'kategori_dokumen',
        'nama_dokumen',
        'file_upload',
        'tipe_file',
    ];

    public function documentable()
    {
        return $this->morphTo();
    }
}

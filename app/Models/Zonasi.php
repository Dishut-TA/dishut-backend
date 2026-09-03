<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Zonasi extends Model
{
    protected $fillable = [
        'kabupaten',
        'kecamatan',
        'desa',
        'file_path'
    ];
}
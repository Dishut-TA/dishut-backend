<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Village extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function lands()
    {
        return $this->hasMany(Land::class);
    }
}

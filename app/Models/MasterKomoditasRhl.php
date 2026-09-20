<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class MasterKomoditasRhl extends Model
{
    protected $table='master_komoditas_rhl';

    protected $fillable=[
        'nama_tanaman','jenis',
        'min_elevation','max_elevation',
        'min_slope','max_slope',
        'min_rainfall','max_rainfall',
        'soil_match'
    ];
}

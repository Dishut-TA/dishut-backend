<?php
namespace App\Services;

class SpatialValidationService
{
    public function validate(array $layers): array
    {
        foreach($layers as $layer){
            if(($layer['size'] ?? 0)>5242880){
                return ['valid'=>false,'message'=>'Ukuran file maksimal 5 MB'];
            }
        }

        $crs=array_unique(array_filter(array_column($layers,'crs')));
        if(count($crs)>1){
            return ['valid'=>false,'message'=>'CRS layer berbeda'];
        }

        return ['valid'=>true];
    }
}

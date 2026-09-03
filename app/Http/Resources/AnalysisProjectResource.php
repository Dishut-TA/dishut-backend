<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AnalysisProjectResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'           => $this->id,
            'kode_project' => $this->project_code,
            'nama_project' => $this->project_name,
            'status'       => $this->status,
            'job_id'       => $this->python_job_id,
            'pesan_error'  => $this->when($this->isFailed(), $this->error_message),

            // Informasi file yang di-upload (hanya nama file, bukan path lengkap untuk keamanan)
            'file_input' => [
                'dem'           => $this->dem_path ? basename($this->dem_path) : null,
                'tutupan_lahan' => $this->landcover_path ? basename($this->landcover_path) : null,
                'curah_hujan'   => $this->rainfall_path ? basename($this->rainfall_path) : null,
                'jenis_tanah'   => $this->soil_path ? basename($this->soil_path) : null,
                'das'           => $this->das_path ? basename($this->das_path) : null,
                'batas_wilayah' => $this->admin_path ? basename($this->admin_path) : null,
            ],

            // Ringkasan hasil analisis (jika sudah selesai)
            'hasil' => $this->when(
                $this->relationLoaded('result') && $this->result,
                fn() => new AnalysisResultResource($this->result)
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

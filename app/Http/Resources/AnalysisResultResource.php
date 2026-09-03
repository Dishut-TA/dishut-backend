<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\AnalysisResultZone;

class AnalysisResultResource extends JsonResource
{
    public function toArray($request)
    {
        // Ambil data zona dari database Laravel yang sudah berisi KTH & CDK
        $zones = AnalysisResultZone::where('result_id', $this->id)->get();

        // Buat pratinjau tabel (maksimal 5 baris) dari database yang sudah ada KTH-nya
        $pratinjauTabel = $zones->isNotEmpty()
            ? $zones->take(5)->map(function ($z) {
                return [
                    "zone_id"               => $z->zone_id,
                    "provinsi"              => $z->provinsi,
                    "kota_kabupaten"        => $z->kabupaten,
                    "kecamatan"             => $z->kecamatan,
                    "desa_kelurahan"        => $z->desa,
                    "status_lahan_kritis"   => $z->status_lahan_kritis,
                    "skor_cpi_rata2"        => $z->skor_cpi,
                    "luas_ha"                => $z->luas_ha, 
                    "rekomendasi_intervensi"=> $z->rekomendasi_intervensi,
                    "cdk"                   => $z->cdk,
                    "nama_kelompok"         => $z->nama_kelompok,
                    "ketua_kelompok"        => $z->ketua_kelompok,
                ];
            })->values()
            : collect($this->table_json ?? [])->take(5)->values();

        return [
            'id'     => $this->id,
            'job_id' => $this->job_id,
            'status' => $this->status,

            // Bobot dan konsistensi AHP
            'ahp' => $this->when($this->getAhpData(), function () {
                $ahp = $this->getAhpData();
                return [
                    'bobot_digunakan'   => $ahp['weights_used'] ?? null,
                    'bobot_asli'       => $ahp['weights_original'] ?? null,
                    'rasio_konsistensi' => $ahp['cr'] ?? null,
                    'konsisten'        => $ahp['is_consistent'] ?? null,
                ];
            }),

            // URL peta dan file output
            'peta' => [
                'geojson_kekritisan' => $this->critical_geojson_url,
                'raster_cpi'         => $this->cpi_raster_url,
                'raster_kelas'       => $this->class_raster_url,
                'preview_html'       => $this->map_html_url,
            ],

            // Endpoint PHP untuk akses data oleh FE
            'endpoint' => [
                'tabel'    => url("/api/projects/{$this->project_id}/table"),
                'peta'     => url("/api/projects/{$this->project_id}/map"),
                'hasil'    => url("/api/projects/{$this->project_id}/result"),
                'unduh'    => url("/api/projects/{$this->project_id}/download"),
            ],

            // Pratinjau tabel zonal (membawa data KTH dari database)
            'pratinjau_tabel' => $pratinjauTabel,
            'jumlah_zona'     => count($this->table_json ?? []),

            // Peringatan dari Python
            'peringatan'   => $this->result_json['warnings'] ?? [],

            // Diagnostik dari Python
            'diagnostik'   => $this->result_json['diagnostics'] ?? null,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
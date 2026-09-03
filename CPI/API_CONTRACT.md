# API Contract untuk PHP dan FE

## PHP ke Python

### Pilihan 1: PHP mengirim file

`POST http://python-service:8000/analysis`

Content-Type: `multipart/form-data`

Field wajib:

- `project_id`
- `dem`
- `landcover`
- `rainfall`
- `soil`
- `das`

Field opsional:

- `zone_api_url`
- `history_api_url`
- `rules_api_url`
- `ahp_matrix`
- `target_resolution`
- `save_intermediate_rasters`

### Pilihan 2: PHP mengirim path

`POST http://python-service:8000/analysis/path`

```json
{
  "project_id": "project_1",
  "dem": "/var/www/storage/project_1/DEM.tif",
  "landcover": "/var/www/storage/project_1/landcover.zip",
  "rainfall": "/var/www/storage/project_1/rainfall.tif",
  "soil": "/var/www/storage/project_1/soil.zip",
  "das": "/var/www/storage/project_1/das.zip",
  "zone_api_url": "http://php/api/zones",
  "history_api_url": "http://php/api/interventions/{zone_id}",
  "target_resolution": 100
}
```

## Python ke PHP

Python mengembalikan:

```json
{
  "job_id": "project_1_20260630120000_ab12cd34",
  "status": "completed",
  "map": {
    "critical_geojson": "/result/project_1_20260630120000_ab12cd34/file/peta_kekritisan_lahan.geojson",
    "cpi_raster": "/result/project_1_20260630120000_ab12cd34/file/cpi_score.tif",
    "class_raster": "/result/project_1_20260630120000_ab12cd34/file/class_kekritisan.tif",
    "html_preview": "/result/project_1_20260630120000_ab12cd34/file/peta_kekritisan_lahan.html"
  },
  "table": [
    {
      "zone_id": "1",
      "provinsi": "Jawa Barat",
      "kota_kabupaten": "Subang",
      "kecamatan": "Ciasem",
      "desa_kelurahan": "Contoh",
      "status_lahan_kritis": "Sangat Kritis",
      "skor_cpi_rata2": 74.2,
      "luas_ha": 120.5,
      "score_landcover_rata2": 4.2,
      "score_rainfall_rata2": 4.0,
      "score_soil_rata2": 3.0,
      "score_slope_rata2": 4.5,
      "slope_percent_rata2": 31.7,
      "alasan_skor": "Status Sangat Kritis karena skor CPI rata-rata 74.2. Faktor dominan: Kemiringan lereng berisiko tinggi.",
      "riwayat_intervensi": "Reboisasi",
      "rekomendasi_intervensi": "Evaluasi keberhasilan reboisasi sebelumnya, lakukan penyulaman tanaman, pengayaan vegetasi, dan pengendalian erosi pada titik prioritas."
    }
  ]
}
```

## FE ke PHP

FE cukup meminta data dari PHP:

- `GET /projects/{project_id}/result`
- `GET /projects/{project_id}/map`
- `GET /projects/{project_id}/table`

PHP boleh menyimpan `response.json` dari Python ke database agar FE tidak perlu langsung memanggil Python.

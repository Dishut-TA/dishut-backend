# Critical Land Engine Final

Workspace ini adalah service Python untuk analisis lahan kritis berbasis AHP dan CPI. Desainnya mengikuti flow final:

1. PHP menerima upload dari user.
2. PHP mengirim lima layer indikator ke Python service.
3. Python melakukan preprocessing, slope extraction, scoring, AHP, CPI, klasifikasi, zonasi, dan rule-based intervention.
4. Python mengembalikan `job_id`, URL GeoJSON, raster, tabel, metadata, dan hasil AHP.
5. PHP menyimpan response ke database.
6. FE mengambil hasil dari PHP atau langsung dari endpoint Python jika diizinkan.

## Input utama dari user

Endpoint `/analysis` menerima `multipart/form-data`:

- `project_id`
- `dem`: GeoTIFF DEM
- `landcover`: raster/vector tutupan lahan. Jika shapefile, upload sebagai ZIP berisi `.shp`, `.shx`, `.dbf`, `.prj`.
- `rainfall`: GeoTIFF curah hujan atau raster skor curah hujan
- `soil`: raster/vector jenis tanah. Jika shapefile, upload ZIP.
- `das`: batas wilayah DAS. Jika shapefile, upload ZIP.

Opsional:

- `zone_api_url`: URL PHP untuk mengambil master zonasi. Response boleh GeoJSON FeatureCollection atau list object dengan field `geometry`.
- `history_api_url`: URL PHP untuk mengambil historical intervention. Boleh memakai `{zone_id}`. Contoh: `http://localhost/api/interventions/{zone_id}`.
- `rules_api_url`: URL PHP untuk mengambil aturan klasifikasi, scoring, atau rekomendasi terbaru.
- `ahp_matrix`: JSON string matrix pairwise AHP.
- `target_resolution`: resolusi proses dalam meter. Gunakan 100 atau 250 untuk proses yang lebih ringan.
- `save_intermediate_rasters`: true/false.

## Output utama

Response `/analysis`:

```json
{
  "job_id": "project_20260630120000_ab12cd34",
  "status": "completed",
  "ahp": {
    "weights_used": {
      "landcover": 0.42,
      "rainfall": 0.23,
      "soil": 0.12,
      "slope": 0.23
    },
    "cr": 0.02,
    "is_consistent": true
  },
  "map": {
    "critical_geojson": "/result/{job_id}/file/peta_kekritisan_lahan.geojson",
    "cpi_raster": "/result/{job_id}/file/cpi_score.tif",
    "class_raster": "/result/{job_id}/file/class_kekritisan.tif",
    "html_preview": "/result/{job_id}/file/peta_kekritisan_lahan.html"
  },
  "table": []
}
```

File output:

- `cpi_score.tif`
- `class_kekritisan.tif`
- `peta_kekritisan_lahan.geojson`
- `peta_kekritisan_lahan.html`
- `tabel_lokasi_kritis.json`
- `tabel_lokasi_kritis.csv`
- `metadata_analisis.json`
- `response.json`

## Instalasi lokal

```bash
python -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
uvicorn app:app --reload --host 0.0.0.0 --port 8000
```

Windows PowerShell:

```powershell
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
uvicorn app:app --reload --host 0.0.0.0 --port 8000
```

Cek service:

```bash
curl http://127.0.0.1:8000/health
```

## Endpoint

### POST `/analysis`

Digunakan PHP jika file dikirim langsung ke Python.

### POST `/analysis/path`

Digunakan PHP jika file sudah disimpan di server dan Python cukup menerima path file.

### GET `/result/{job_id}`

Mengambil response lengkap.

### GET `/result/{job_id}/table`

Mengambil tabel dashboard.

### GET `/result/{job_id}/map`

Mengambil GeoJSON peta lahan kritis.

### GET `/result/{job_id}/file/{filename}`

Mengambil file output spesifik.

## Format master data PHP

### Zonasi

`GET /api/zones`

Bisa GeoJSON:

```json
{
  "type": "FeatureCollection",
  "features": [
    {
      "type": "Feature",
      "properties": {
        "id": 1,
        "provinsi": "Jawa Barat",
        "kabupaten": "Subang",
        "kecamatan": "Ciasem",
        "desa": "Contoh"
      },
      "geometry": {"type": "Polygon", "coordinates": []}
    }
  ]
}
```

Atau list object:

```json
[
  {
    "id": 1,
    "provinsi": "Jawa Barat",
    "kabupaten": "Subang",
    "kecamatan": "Ciasem",
    "desa": "Contoh",
    "geometry": {"type": "Polygon", "coordinates": []}
  }
]
```

### Historical intervention

`GET /api/interventions/{zone_id}`

```json
{
  "history": ["Reboisasi", "Agroforestri"]
}
```

## Catatan penting

1. Data batas administrasi atau zonasi tidak perlu di-upload user setiap analisis. Data ini lebih cocok menjadi master database PHP.
2. DAS tetap di-upload sebagai area clipping dan batas analisis.
3. Jika PHP belum punya PostGIS, simpan geometry sebagai GeoJSON dan kirim ke Python lewat `zone_api_url`.
4. Nilai skor pada `config/rules.yaml` masih perlu disesuaikan dengan pedoman atau Permen LHK yang dipakai di skripsi.
5. AHP matrix default hanya template. Untuk penelitian final, ganti dengan judgement ahli atau matriks dari dosen/pakar.

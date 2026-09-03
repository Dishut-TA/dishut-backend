$payload = @{
  project_id = "test_001"
  dem = "D:/Skripsi/Teknis/cpi_ahp_engine_python/data/DEM.tif"
  landcover = "D:/Skripsi/Teknis/cpi_ahp_engine_python/data/tutupan_lahan.tif"
  rainfall = "D:/Skripsi/Teknis/cpi_ahp_engine_python/data/rainfall_score.tif"
  soil = "D:/Skripsi/Teknis/cpi_ahp_engine_python/data/jenis_tanah.zip"
  das = "D:/Skripsi/Teknis/cpi_ahp_engine_python/data/DAS.zip"
  target_resolution = 5000
} | ConvertTo-Json -Depth 5

curl.exe -X POST "http://127.0.0.1:8000/analysis/path" `
  -H "Content-Type: application/json" `
  --data-binary $payload

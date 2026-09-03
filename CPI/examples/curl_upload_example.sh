#!/usr/bin/env bash
curl -X POST "http://127.0.0.1:8000/analysis" \
  -F "project_id=project_1" \
  -F "target_resolution=100" \
  -F "zone_api_url=http://localhost/api/zones" \
  -F "history_api_url=http://localhost/api/interventions/{zone_id}" \
  -F "dem=@/path/storage/project_1/DEM.tif" \
  -F "landcover=@/path/storage/project_1/landcover.zip" \
  -F "rainfall=@/path/storage/project_1/rainfall.tif" \
  -F "soil=@/path/storage/project_1/soil.zip" \
  -F "das=@/path/storage/project_1/das.zip"

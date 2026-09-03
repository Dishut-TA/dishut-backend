from __future__ import annotations

import json
import os
import shutil
import time
import traceback
import uuid
import zipfile
from dataclasses import asdict
from pathlib import Path
from typing import Any, Optional

import geopandas as gpd
import pandas as pd
import requests
import yaml
from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import FileResponse, JSONResponse
from pydantic import BaseModel

from cpi_engine.engine import CPIAHPEngine
from cpi_engine.intervention import apply_intervention_rules

BASE_DIR = Path(os.getenv("CRITICAL_LAND_BASE_DIR", Path(__file__).resolve().parent))
UPLOADS_DIR = Path(os.getenv("CRITICAL_LAND_UPLOADS_DIR", BASE_DIR / "uploads"))
OUTPUTS_DIR = Path(os.getenv("CRITICAL_LAND_OUTPUTS_DIR", BASE_DIR / "outputs"))
CONFIG_PATH = Path(os.getenv("CRITICAL_LAND_CONFIG", BASE_DIR / "config" / "rules.yaml"))
PUBLIC_BASE_URL = os.getenv("CRITICAL_LAND_PUBLIC_BASE_URL", "").rstrip("/")

UPLOADS_DIR.mkdir(parents=True, exist_ok=True)
OUTPUTS_DIR.mkdir(parents=True, exist_ok=True)

app = FastAPI(title="Critical Land AHP-CPI Service", version="2.0.0")
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


class PathRunRequest(BaseModel):
    project_id: str
    dem: str
    landcover: str
    rainfall: str
    soil: str
    das: str
    admin: Optional[str] = None
    out: Optional[str] = None
    zone_api_url: Optional[str] = None
    history_api_url: Optional[str] = None
    rules_api_url: Optional[str] = None
    ahp_matrix: Optional[list[list[float]]] = None
    target_resolution: Optional[float] = None
    save_intermediate_rasters: bool = False


def _safe_name(name: str | None, fallback: str) -> str:
    raw = name or fallback
    cleaned = "".join(c for c in raw if c.isalnum() or c in "._- ").strip().replace(" ", "_")
    return cleaned or fallback


def _job_id(project_id: str) -> str:
    safe_project = _safe_name(project_id, "project")
    return f"{safe_project}_{time.strftime('%Y%m%d%H%M%S')}_{uuid.uuid4().hex[:8]}"


def _save_upload(upload: UploadFile, target_dir: Path, logical_name: str) -> Path:
    target_dir.mkdir(parents=True, exist_ok=True)
    filename = _safe_name(upload.filename, f"{logical_name}.dat")
    dest = target_dir / filename
    with dest.open("wb") as f:
        shutil.copyfileobj(upload.file, f)
    return _resolve_spatial_path(dest, logical_name)


def _resolve_spatial_path(path: Path, logical_name: str) -> Path:
    suffix = path.suffix.lower()
    if suffix == ".zip":
        extract_dir = path.parent / f"{path.stem}_extracted"
        extract_dir.mkdir(parents=True, exist_ok=True)
        with zipfile.ZipFile(path) as zf:
            zf.extractall(extract_dir)
        candidates = list(extract_dir.rglob("*"))
        # Prefer shapefile for vector uploads, then GeoPackage/GeoJSON, then raster.
        priority = [".shp", ".gpkg", ".geojson", ".json", ".tif", ".tiff"]
        for ext in priority:
            matches = [p for p in candidates if p.is_file() and p.suffix.lower() == ext]
            if matches:
                return matches[0]
        raise HTTPException(status_code=400, detail=f"ZIP {path.name} tidak berisi file spasial yang didukung untuk {logical_name}.")
    return path


def _load_cfg_copy(job_dir: Path, ahp_matrix: str | list[list[float]] | None, rules_api_url: str | None) -> Path:
    cfg = yaml.safe_load(CONFIG_PATH.read_text(encoding="utf-8"))
    if ahp_matrix:
        if isinstance(ahp_matrix, str):
            ahp_matrix = json.loads(ahp_matrix)
        cfg["ahp"]["pairwise_matrix"] = ahp_matrix
    if rules_api_url:
        try:
            remote_rules = requests.get(rules_api_url, timeout=30).json()
            if isinstance(remote_rules, dict):
                # Merge only selected sections to avoid breaking project settings.
                for key in ["classification", "rules", "recommendations", "admin_fields"]:
                    if key in remote_rules:
                        cfg[key] = remote_rules[key]
        except Exception as exc:
            raise HTTPException(status_code=502, detail=f"Gagal mengambil rules_api_url: {exc}") from exc
    out = job_dir / "rules_used.yaml"
    out.write_text(yaml.safe_dump(cfg, allow_unicode=True, sort_keys=False), encoding="utf-8")
    return out


def _fetch_zones(zone_api_url: str, job_dir: Path) -> Path:
    try:
        data = requests.get(zone_api_url, timeout=60).json()
    except Exception as exc:
        raise HTTPException(status_code=502, detail=f"Gagal mengambil zone_api_url: {exc}") from exc

    out = job_dir / "zones_master.geojson"
    if isinstance(data, dict) and data.get("type") == "FeatureCollection":
        out.write_text(json.dumps(data, ensure_ascii=False), encoding="utf-8")
        return out

    if isinstance(data, list):
        features = []
        for item in data:
            if not isinstance(item, dict):
                continue
            props = dict(item)
            geom = props.pop("geometry", None) or props.pop("geom", None)
            if isinstance(geom, str):
                geom = json.loads(geom)
            if not geom:
                continue
            features.append({"type": "Feature", "properties": props, "geometry": geom})
        feature_collection = {"type": "FeatureCollection", "features": features}
        out.write_text(json.dumps(feature_collection, ensure_ascii=False), encoding="utf-8")
        return out

    raise HTTPException(status_code=400, detail="Format response zone_api_url harus GeoJSON FeatureCollection atau list object berisi geometry.")


def _history_url(base: str, zone_id: str) -> str:
    if "{zone_id}" in base:
        return base.replace("{zone_id}", str(zone_id))
    return f"{base.rstrip('/')}/{zone_id}"


def _fetch_histories(history_api_url: str | None, table_rows: list[dict[str, Any]]) -> dict[str, list[str]]:
    if not history_api_url:
        return {}
    histories: dict[str, list[str]] = {}
    for row in table_rows:
        zone_id = str(row.get("zone_id", "") or "")
        if not zone_id or zone_id == "-" or zone_id in histories:
            continue
        try:
            payload = requests.get(_history_url(history_api_url, zone_id), timeout=20).json()
            raw_history = payload.get("history", payload) if isinstance(payload, dict) else payload
            if isinstance(raw_history, list):
                histories[zone_id] = [str(x.get("intervensi", x)) if isinstance(x, dict) else str(x) for x in raw_history]
            else:
                histories[zone_id] = []
        except Exception:
            histories[zone_id] = []
    return histories


def _public_url(job_id: str, filename: str) -> str:
    if PUBLIC_BASE_URL:
        return f"{PUBLIC_BASE_URL}/result/{job_id}/file/{filename}"
    return f"/result/{job_id}/file/{filename}"


def _diagnostics(job_id: str, table_rows: list[dict[str, Any]]) -> dict[str, Any]:
    out_dir = OUTPUTS_DIR / job_id
    geojson_path = out_dir / "peta_kekritisan_lahan.geojson"
    diag: dict[str, Any] = {"table_rows": len(table_rows)}
    if geojson_path.exists():
        try:
            gj = json.loads(geojson_path.read_text(encoding="utf-8"))
            diag["geojson_features"] = len(gj.get("features", []))
            diag["geojson_first_properties"] = gj.get("features", [{}])[0].get("properties", {}) if gj.get("features") else {}
        except Exception as exc:
            diag["geojson_error"] = str(exc)
    for fname in ["cpi_score.tif", "class_kekritisan.tif"]:
        fpath = out_dir / fname
        if fpath.exists():
            diag[fname] = {"size_bytes": fpath.stat().st_size}
    return diag


def _result_payload(job_id: str, result: Any, table_rows: list[dict[str, Any]]) -> dict[str, Any]:
    paths = result.paths
    files = {key: Path(value).name for key, value in paths.items()}
    return {
        "job_id": job_id,
        "status": "completed",
        "output_dir": str(OUTPUTS_DIR / job_id),
        "ahp": result.ahp,
        "warnings": result.warnings,
        "map": {
            "critical_geojson": _public_url(job_id, files.get("geojson", "peta_kekritisan_lahan.geojson")),
            "cpi_raster": _public_url(job_id, files.get("cpi_score", "cpi_score.tif")),
            "class_raster": _public_url(job_id, files.get("class_raster", "class_kekritisan.tif")),
            "html_preview": _public_url(job_id, files.get("map_html", "peta_kekritisan_lahan.html")),
        },
        "table": table_rows,
        "files": {key: _public_url(job_id, name) for key, name in files.items()},
        "diagnostics": _diagnostics(job_id, table_rows),
    }


def _run_engine(
    *,
    project_id: str,
    dem_path: Path,
    landcover_path: Path,
    rainfall_path: Path,
    soil_path: Path,
    das_path: Path,
    admin_path: Path | None,
    job_id: str,
    zone_api_url: str | None,
    history_api_url: str | None,
    rules_api_url: str | None,
    ahp_matrix: str | list[list[float]] | None,
    target_resolution: float | None,
    save_intermediate_rasters: bool,
) -> dict[str, Any]:
    job_upload_dir = UPLOADS_DIR / job_id
    out_dir = OUTPUTS_DIR / job_id
    job_upload_dir.mkdir(parents=True, exist_ok=True)
    out_dir.mkdir(parents=True, exist_ok=True)

    # Path endpoint may receive ZIP shapefiles. Resolve them here too.
    dem_path = _resolve_spatial_path(Path(dem_path), "dem")
    landcover_path = _resolve_spatial_path(Path(landcover_path), "landcover")
    rainfall_path = _resolve_spatial_path(Path(rainfall_path), "rainfall")
    soil_path = _resolve_spatial_path(Path(soil_path), "soil")
    das_path = _resolve_spatial_path(Path(das_path), "das")
    admin_path = _resolve_spatial_path(Path(admin_path), "admin") if admin_path else None

    cfg_path = _load_cfg_copy(job_upload_dir, ahp_matrix, rules_api_url)
    zone_path = _fetch_zones(zone_api_url, job_upload_dir) if zone_api_url else None
    selected_admin_path = zone_path or admin_path

    engine = CPIAHPEngine(cfg_path)
    result = engine.run(
        dem_path=dem_path,
        rainfall_path=rainfall_path,
        soil_path=soil_path,
        landcover_path=landcover_path,
        aoi_path=das_path,
        admin_path=selected_admin_path,
        out_dir=out_dir,
        allow_missing_indicators=False,
        target_resolution_override=target_resolution,
        save_intermediate_rasters=save_intermediate_rasters,
    )

    table_path = out_dir / "tabel_lokasi_kritis.json"
    table_rows = json.loads(table_path.read_text(encoding="utf-8")) if table_path.exists() else []
    histories = _fetch_histories(history_api_url, table_rows)
    if histories:
        df = pd.DataFrame(table_rows)
        df = apply_intervention_rules(df, engine.cfg, histories)
        df.to_json(table_path, orient="records", force_ascii=False, indent=2)
        df.to_csv(out_dir / "tabel_lokasi_kritis.csv", index=False)
        table_rows = json.loads(table_path.read_text(encoding="utf-8"))

    payload = _result_payload(job_id, result, table_rows)
    (out_dir / "response.json").write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    return payload


def _dev_error(exc: Exception) -> HTTPException:
    """Return readable engine errors during local development.

    In production, replace this with generic logging if stack traces must not be exposed.
    """
    return HTTPException(
        status_code=500,
        detail={
            "error_type": type(exc).__name__,
            "message": str(exc),
            "traceback": traceback.format_exc().splitlines()[-12:],
        },
    )


@app.get("/health")
def health() -> dict[str, Any]:
    return {"status": "ok", "service": "critical-land-ahp-cpi", "version": "2.0.0"}

@app.post("/extract-zonasi")
async def extract_zonasi(
    file: UploadFile = File(...), 
):
    file_path = f"temp_{file.filename}"
    with open(file_path, "wb") as buffer:
        shutil.copyfileobj(file.file, buffer)
        
    try:
        # Baca SHP dari dalam ZIP
        gdf = gpd.read_file(f"zip://{file_path}")
        
        data_list = []
        for index, row in gdf.iterrows():
            # Menggunakan nama kolom persis seperti di Mapshaper
            kabupaten = row.get("WADMKK", "")
            kecamatan = row.get("WADMKC", "")
            desa = row.get("NAMOBJ", "")
            
            # Skip jika baris ini kosong (mencegah data error masuk)
            if not kabupaten and not kecamatan and not desa:
                continue
                
            data_list.append({
                "kabupaten": kabupaten,
                "kecamatan": kecamatan,
                "desa": desa,
            })
            
        return {"status": "success", "data": data_list}

    except Exception as e:
        return {"status": "error", "message": str(e)}
    finally:
        if os.path.exists(file_path):
            os.remove(file_path)


@app.post("/analysis")
async def analysis_upload(
    project_id: str = Form(...),
    dem: UploadFile = File(...),
    landcover: UploadFile = File(...),
    rainfall: UploadFile = File(...),
    soil: UploadFile = File(...),
    das: UploadFile = File(...),
    admin: UploadFile | None = File(None),
    zone_api_url: str | None = Form(None),
    history_api_url: str | None = Form(None),
    rules_api_url: str | None = Form(None),
    ahp_matrix: str | None = Form(None),
    target_resolution: float | None = Form(None),
    save_intermediate_rasters: bool = Form(False),
) -> dict[str, Any]:
    """Main endpoint for PHP.

    PHP sends multipart/form-data containing five uploaded layers.
    Vector layers may be uploaded as .zip containing .shp, .shx, .dbf, .prj.
    """
    job_id = _job_id(project_id)
    job_upload_dir = UPLOADS_DIR / job_id
    job_upload_dir.mkdir(parents=True, exist_ok=True)

    dem_path = _save_upload(dem, job_upload_dir, "dem")
    landcover_path = _save_upload(landcover, job_upload_dir, "landcover")
    rainfall_path = _save_upload(rainfall, job_upload_dir, "rainfall")
    soil_path = _save_upload(soil, job_upload_dir, "soil")
    das_path = _save_upload(das, job_upload_dir, "das")
    admin_path = _save_upload(admin, job_upload_dir, "admin") if admin else None

    try:
        return _run_engine(
            project_id=project_id,
            dem_path=dem_path,
            landcover_path=landcover_path,
            rainfall_path=rainfall_path,
            soil_path=soil_path,
            das_path=das_path,
            admin_path=admin_path,
            job_id=job_id,
            zone_api_url=zone_api_url,
            history_api_url=history_api_url,
            rules_api_url=rules_api_url,
            ahp_matrix=ahp_matrix,
            target_resolution=target_resolution,
            save_intermediate_rasters=save_intermediate_rasters,
        )
    except HTTPException:
        raise
    except Exception as exc:
        raise _dev_error(exc) from exc


@app.post("/analysis/path")
def analysis_path(req: PathRunRequest) -> dict[str, Any]:
    """Alternative endpoint when PHP already saved files and only sends file paths."""
    job_id = _job_id(req.project_id)
    (UPLOADS_DIR / job_id).mkdir(parents=True, exist_ok=True)
    try:
        return _run_engine(
            project_id=req.project_id,
            dem_path=Path(req.dem),
            landcover_path=Path(req.landcover),
            rainfall_path=Path(req.rainfall),
            soil_path=Path(req.soil),
            das_path=Path(req.das),
            admin_path=Path(req.admin) if req.admin else None,
            job_id=job_id,
            zone_api_url=req.zone_api_url,
            history_api_url=req.history_api_url,
            rules_api_url=req.rules_api_url,
            ahp_matrix=req.ahp_matrix,
            target_resolution=req.target_resolution,
            save_intermediate_rasters=req.save_intermediate_rasters,
        )
    except HTTPException:
        raise
    except Exception as exc:
        raise _dev_error(exc) from exc


@app.get("/result/{job_id}")
def result(job_id: str) -> JSONResponse:
    path = OUTPUTS_DIR / job_id / "response.json"
    if not path.exists():
        raise HTTPException(status_code=404, detail="Job result not found")
    return JSONResponse(json.loads(path.read_text(encoding="utf-8")))


@app.get("/result/{job_id}/table")
def result_table(job_id: str) -> JSONResponse:
    path = OUTPUTS_DIR / job_id / "tabel_lokasi_kritis.json"
    if not path.exists():
        raise HTTPException(status_code=404, detail="Table result not found")
    return JSONResponse(json.loads(path.read_text(encoding="utf-8")))


@app.get("/result/{job_id}/map")
def result_map(job_id: str) -> JSONResponse:
    path = OUTPUTS_DIR / job_id / "peta_kekritisan_lahan.geojson"
    if not path.exists():
        raise HTTPException(status_code=404, detail="GeoJSON result not found")
    return JSONResponse(json.loads(path.read_text(encoding="utf-8")))


@app.get("/result/{job_id}/file/{filename}")
def result_file(job_id: str, filename: str) -> FileResponse:
    safe = _safe_name(filename, "file")
    path = OUTPUTS_DIR / job_id / safe
    if not path.exists() or not path.is_file():
        raise HTTPException(status_code=404, detail="File not found")
    return FileResponse(path)

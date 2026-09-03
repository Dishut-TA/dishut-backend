from __future__ import annotations

import json
from pathlib import Path
from typing import Any, Dict, Mapping, Optional

import folium
import geopandas as gpd
import numpy as np
import pandas as pd
from rasterio import features
from shapely.geometry import shape

from .cpi import class_lookup
from .geo import RasterReference, read_vector


INDICATOR_LABELS = {
    "landcover": "Tutupan lahan",
    "rainfall": "Curah hujan",
    "soil": "Jenis tanah",
    "slope": "Kemiringan lereng",
}


def polygonize_classes(class_arr: np.ndarray, ref: RasterReference, thresholds: Dict[str, Any], simplify_tolerance: float = 0) -> gpd.GeoDataFrame:
    lookup = class_lookup(thresholds)
    mask = class_arr > 0
    rows = []
    for geom, val in features.shapes(class_arr.astype("uint8"), mask=mask, transform=ref.transform):
        cid = int(val)
        spec = lookup.get(cid)
        if spec is None:
            continue
        geom_obj = shape(geom)
        if simplify_tolerance and simplify_tolerance > 0:
            geom_obj = geom_obj.simplify(simplify_tolerance, preserve_topology=True)
        rows.append({
            "class_id": cid,
            "status": spec["label"],
            "color": spec["color"],
            "geometry": geom_obj,
        })
    if not rows:
        return gpd.GeoDataFrame(columns=["class_id", "status", "color", "geometry"], geometry="geometry", crs=ref.crs)
    gdf = gpd.GeoDataFrame(rows, geometry="geometry", crs=ref.crs)
    return gdf.dissolve(by=["class_id", "status", "color"], as_index=False)


def pick_admin_fields(admin_gdf: gpd.GeoDataFrame, cfg: Dict[str, Any]) -> Dict[str, Optional[str]]:
    # 1. Ambil config dari yaml jika ada
    field_cfg = cfg.get("admin_fields", {})
    
    # 2. Siapkan standar nama BIG (Mapshaper) dan penamaan umum sebagai jaring pengaman
    default_fields = {
        "zone_id": ["id", "zone_id", "fid", "objectid"], # fid & objectid otomatis jadi zone_id
        "province": ["WADMPR", "provinsi", "prov"],
        "city": ["WADMKK", "kabupaten", "kota_kabupaten", "kab_kota"],
        "district": ["WADMKC", "kecamatan", "kec"],
        "village": ["NAMOBJ", "WADMKD", "desa", "desa_kelurahan", "kelurahan"]
    }

    # 3. Gabungkan config yaml dengan default standar BIG
    for key, candidates in default_fields.items():
        if key not in field_cfg:
            field_cfg[key] = candidates
        else:
            # Tambahkan standar BIG ke dalam config jika belum tertulis
            for cand in candidates:
                if cand not in field_cfg[key]:
                    field_cfg[key].append(cand)

    # 4. Cocokkan dengan kolom yang benar-benar ada di file SHP user
    existing = {c.lower(): c for c in admin_gdf.columns if c.lower() != "geometry"}
    out: Dict[str, Optional[str]] = {}
    
    for logical, candidates in field_cfg.items():
        out[logical] = None
        for cand in candidates:
            if cand.lower() in existing:
                out[logical] = existing[cand.lower()]
                break
                
    return out


def _zonal_mean_from_raster(gdf: gpd.GeoDataFrame, raster_arr: np.ndarray, ref: RasterReference) -> list[float]:
    """Compatibility helper for non-admin polygon summaries.

    This remains polygon-by-polygon and is only used for small class polygons.
    Admin zonal statistics uses the faster one-pass zone raster method below.
    """
    means: list[float] = []
    for geom in gdf.geometry:
        mask = features.rasterize(
            [(geom, 1)],
            out_shape=ref.shape,
            transform=ref.transform,
            fill=0,
            dtype="uint8",
            all_touched=True,
        ).astype(bool)
        vals = raster_arr[mask]
        vals = vals[np.isfinite(vals)]
        if ref.nodata is not None:
            vals = vals[vals != ref.nodata]
        means.append(float(np.nanmean(vals)) if vals.size else float("nan"))
    return means


def _zone_raster(admin: gpd.GeoDataFrame, ref: RasterReference) -> np.ndarray:
    """Rasterize every admin polygon once.

    Each polygon gets a unique integer ID. This avoids rasterizing the full raster
    repeatedly for every polygon and makes zonal statistics much faster.
    """
    shapes = []
    for idx, geom in enumerate(admin.geometry, start=1):
        if geom is None or geom.is_empty:
            continue
        shapes.append((geom, idx))
    if not shapes:
        return np.zeros(ref.shape, dtype="int32")
    return features.rasterize(
        shapes,
        out_shape=ref.shape,
        transform=ref.transform,
        fill=0,
        dtype="int32",
        all_touched=True,
    )


def _aggregate_by_zone(zone_arr: np.ndarray, value_arr: np.ndarray, ref: RasterReference, zone_count: int) -> tuple[np.ndarray, np.ndarray]:
    """Return mean value and valid pixel count for every admin zone.

    Output arrays use zero-based order matching admin row order.
    """
    zones = zone_arr.ravel()
    values = value_arr.ravel().astype("float64", copy=False)
    valid = zones > 0
    valid &= np.isfinite(values)
    if ref.nodata is not None:
        valid &= values != ref.nodata

    if not np.any(valid):
        return np.full(zone_count, np.nan, dtype="float64"), np.zeros(zone_count, dtype="int64")

    counts = np.bincount(zones[valid], minlength=zone_count + 1)
    sums = np.bincount(zones[valid], weights=values[valid], minlength=zone_count + 1)

    means = np.full(zone_count + 1, np.nan, dtype="float64")
    nz = counts > 0
    means[nz] = sums[nz] / counts[nz]
    return means[1:], counts[1:]

def _safe_round_series(series: pd.Series, digits: int = 2) -> pd.Series:
    return pd.to_numeric(series, errors="coerce").round(digits)


def _add_indicator_means(
    gdf: gpd.GeoDataFrame,
    ref: RasterReference,
    indicator_scores: Mapping[str, np.ndarray] | None,
    slope_percent: np.ndarray | None,
) -> gpd.GeoDataFrame:
    gdf = gdf.copy()
    if slope_percent is not None:
        gdf["slope_percent_rata2"] = _zonal_mean_from_raster(gdf, slope_percent, ref)
    if indicator_scores:
        for name, arr in indicator_scores.items():
            gdf[f"score_{name}_rata2"] = _zonal_mean_from_raster(gdf, arr, ref)
    return gdf


def _build_reason(row: pd.Series) -> str:
    cpi = row.get("skor_cpi_rata2")
    status = row.get("status_lahan_kritis", "-")
    drivers = []
    for name, label in INDICATOR_LABELS.items():
        val = row.get(f"score_{name}_rata2")
        try:
            val_f = float(val)
        except Exception:
            continue
        if np.isfinite(val_f) and val_f >= 4:
            drivers.append(f"{label} berisiko tinggi")
        elif np.isfinite(val_f) and val_f >= 3.5:
            drivers.append(f"{label} cukup berisiko")
    if drivers:
        return f"Status {status} karena skor CPI rata-rata {cpi}. Faktor dominan: {', '.join(drivers)}."
    return f"Status {status} karena skor CPI rata-rata {cpi} berdasarkan bobot AHP dan klasifikasi CPI."


def default_recommendation(status: str, cfg: Dict[str, Any]) -> str:
    rec = cfg.get("recommendations", {})
    if status == "Tidak Kritis":
        return rec.get("tidak_kritis", {}).get("default", "Monitoring berkala dan pemeliharaan tutupan vegetasi.")
    if status == "Kritis":
        return rec.get("kritis", {}).get("default", "Konservasi vegetatif dan rehabilitasi ringan-sedang.")
    if status == "Sangat Kritis":
        return rec.get("sangat_kritis", {}).get("default", "Rehabilitasi prioritas dan pengendalian erosi.")
    return "Perlu evaluasi lapangan."


def _classify_mean_cpi(value: float, thresholds: Dict[str, Any]) -> Dict[str, Any] | None:
    if value is None or not np.isfinite(value):
        return None
    for _, spec in thresholds.items():
        lo = float(spec["min"])
        hi = float(spec["max"])
        # Include the upper boundary for the last class.
        if (value >= lo and value < hi) or (value == hi and hi >= 100):
            return {
                "class_id": int(spec["class_id"]),
                "status": spec.get("label", "-"),
                "color": spec.get("color", "#777777"),
            }
    return None


def build_admin_zonal_gdf(
    cpi_arr: np.ndarray,
    ref: RasterReference,
    cfg: Dict[str, Any],
    admin_path: str | Path | None,
    aoi_path: str | Path | None = None,
    fallback_crs: str | None = None,
    indicator_scores: Mapping[str, np.ndarray] | None = None,
    slope_percent: np.ndarray | None = None,
) -> gpd.GeoDataFrame:
    """Build a fast zonal map from administrative polygons.

    Fast strategy:
    1. Read admin polygons.
    2. BBox-filter admin polygons to the raster/AOI extent.
    3. Rasterize admin polygons ONCE into zone IDs.
    4. Aggregate CPI and indicator arrays with np.bincount.

    This is much faster than masking/rasterizing every polygon one by one.
    """
    columns = [
        "zone_id", "provinsi", "kota_kabupaten", "kecamatan", "desa_kelurahan",
        "class_id", "status", "status_lahan_kritis", "color", "skor_cpi_rata2", "luas_ha", "geometry",
    ]
    if not admin_path:
        return gpd.GeoDataFrame(columns=columns, geometry="geometry", crs=ref.crs)

    admin = read_vector(admin_path, fallback_crs=fallback_crs).to_crs(ref.crs)
    admin = admin[admin.geometry.notnull()].copy()
    if admin.empty:
        return gpd.GeoDataFrame(columns=columns, geometry="geometry", crs=ref.crs)

    # Fast spatial pre-filter. Avoid expensive full gpd.clip here.
    # The raster grid is already built from AOI, so non-overlapping polygons
    # naturally receive zero valid pixels and are removed after aggregation.
    try:
        minx, miny, maxx, maxy = ref.bounds
        admin = admin.cx[minx:maxx, miny:maxy].copy()
    except Exception:
        pass

    # Optional AOI bbox filter. Do not run polygon clip because that is slow for
    # thousands of detailed village polygons.
    if aoi_path:
        try:
            aoi = read_vector(aoi_path, fallback_crs=fallback_crs).to_crs(ref.crs)
            minx, miny, maxx, maxy = aoi.total_bounds
            admin = admin.cx[minx:maxx, miny:maxy].copy()
        except Exception:
            pass

    if admin.empty:
        return gpd.GeoDataFrame(columns=columns, geometry="geometry", crs=ref.crs)

    # Reset order because zone ID must match row index + 1.
    admin = admin.reset_index(drop=True)
    zone_count = len(admin)
    zone_arr = _zone_raster(admin, ref)

    cpi_means, cpi_counts = _aggregate_by_zone(zone_arr, cpi_arr, ref, zone_count)
    out = admin.copy()
    out["skor_cpi_rata2"] = cpi_means
    out["_valid_pixel_count"] = cpi_counts
    out = out[np.isfinite(out["skor_cpi_rata2"]) & (out["_valid_pixel_count"] > 0)].copy()
    if out.empty:
        return gpd.GeoDataFrame(columns=columns, geometry="geometry", crs=ref.crs)

    # Rebuild zone raster aggregation arrays by original admin order.
    # out keeps original row indices from admin, so use those to map means.
    active_indices = out.index.to_numpy()

    if slope_percent is not None:
        means, _ = _aggregate_by_zone(zone_arr, slope_percent, ref, zone_count)
        out["slope_percent_rata2"] = means[active_indices]

    if indicator_scores:
        for name, arr in indicator_scores.items():
            means, _ = _aggregate_by_zone(zone_arr, arr, ref, zone_count)
            out[f"score_{name}_rata2"] = means[active_indices]

    out["skor_cpi_rata2"] = _safe_round_series(out["skor_cpi_rata2"], 2)

    # Area reflects valid raster cells inside the AOI, not the full admin polygon.
    pixel_area = abs(float(ref.transform.a) * float(ref.transform.e))
    out["luas_ha"] = (out["_valid_pixel_count"].astype(float) * pixel_area / 10000.0).round(2)

    fields = pick_admin_fields(out, cfg)
    logical_to_output = {
        "zone_id": "zone_id",
        "province": "provinsi",
        "city": "kota_kabupaten",
        "district": "kecamatan",
        "village": "desa_kelurahan",
    }
    for logical, out_col in logical_to_output.items():
        src = fields.get(logical)
        out[out_col] = out[src] if src else "-"

    classified = out["skor_cpi_rata2"].map(lambda v: _classify_mean_cpi(float(v), cfg["classification"]["thresholds"]))
    out["class_id"] = classified.map(lambda x: x["class_id"] if x else 0)
    out["status"] = classified.map(lambda x: x["status"] if x else "-")
    out["status_lahan_kritis"] = out["status"]
    out["color"] = classified.map(lambda x: x["color"] if x else "#777777")
    out["alasan_skor"] = out.apply(_build_reason, axis=1)
    out["rekomendasi_intervensi"] = out["status_lahan_kritis"].map(lambda s: default_recommendation(str(s), cfg))

    base_cols = [
        "zone_id", "provinsi", "kota_kabupaten", "kecamatan", "desa_kelurahan",
        "class_id", "status", "status_lahan_kritis", "color", "skor_cpi_rata2", "luas_ha",
        "alasan_skor", "rekomendasi_intervensi",
    ]
    extra_cols = [c for c in out.columns if (c.startswith("score_") and c.endswith("_rata2")) or c == "slope_percent_rata2"]
    for col in extra_cols:
        out[col] = _safe_round_series(out[col], 2)

    # Simplify dashboard geometry before saving GeoJSON/HTML. Detailed village
    # boundaries can make Folium and GeoJSON export look stuck. The tolerance is
    # based on the raster resolution and config value, so coarser analysis gets
    # coarser display geometry.
    try:
        cfg_tol = float(cfg.get("project", {}).get("output_simplify_tolerance", 0) or 0)
        res_tol = max(abs(float(ref.transform.a)), abs(float(ref.transform.e))) * 0.25
        simplify_tolerance = max(cfg_tol, res_tol)
        if simplify_tolerance > 0:
            out["geometry"] = out.geometry.simplify(simplify_tolerance, preserve_topology=True)
    except Exception:
        pass

    keep = base_cols + extra_cols + ["geometry"]
    return out[keep].reset_index(drop=True)

def build_summary_table(
    class_gdf: gpd.GeoDataFrame,
    cpi_arr: np.ndarray,
    ref: RasterReference,
    cfg: Dict[str, Any],
    admin_path: str | Path | None = None,
    aoi_path: str | Path | None = None,
    fallback_crs: str | None = None,
    indicator_scores: Mapping[str, np.ndarray] | None = None,
    slope_percent: np.ndarray | None = None,
) -> pd.DataFrame:
    """Create dashboard table.

    If admin_path is provided, the function overlays CPI classes with zone or admin polygons.
    The admin_path can be a local master zone GeoJSON fetched from PHP, a shapefile, GPKG, or another supported vector file.
    """
    if class_gdf.empty:
        return pd.DataFrame(columns=[
            "zone_id", "provinsi", "kota_kabupaten", "kecamatan", "desa_kelurahan",
            "status_lahan_kritis", "skor_cpi_rata2", "luas_ha", "alasan_skor", "rekomendasi_intervensi",
        ])

    class_gdf = class_gdf.to_crs(ref.crs).copy()
    class_gdf["luas_ha"] = class_gdf.geometry.area / 10000.0
    class_gdf["skor_cpi_rata2"] = _zonal_mean_from_raster(class_gdf, cpi_arr, ref)
    class_gdf = _add_indicator_means(class_gdf, ref, indicator_scores, slope_percent)

    if admin_path:
        admin = read_vector(admin_path, fallback_crs=fallback_crs).to_crs(ref.crs)
        fields = pick_admin_fields(admin, cfg)
        keep_cols = [v for v in fields.values() if v] + ["geometry"]
        keep_cols = list(dict.fromkeys(keep_cols))
        admin = admin[keep_cols].copy()
        overlay = gpd.overlay(admin, class_gdf.drop(columns=["luas_ha", "skor_cpi_rata2"], errors="ignore"), how="intersection")
        if overlay.empty:
            return pd.DataFrame(columns=[
                "zone_id", "provinsi", "kota_kabupaten", "kecamatan", "desa_kelurahan",
                "status_lahan_kritis", "skor_cpi_rata2", "luas_ha", "alasan_skor", "rekomendasi_intervensi",
            ])
        overlay["luas_ha"] = overlay.geometry.area / 10000.0
        overlay["skor_cpi_rata2"] = _zonal_mean_from_raster(overlay, cpi_arr, ref)
        overlay = _add_indicator_means(overlay, ref, indicator_scores, slope_percent)

        logical_to_output = {
            "zone_id": "zone_id",
            "province": "provinsi",
            "city": "kota_kabupaten",
            "district": "kecamatan",
            "village": "desa_kelurahan",
        }
        for logical, out_col in logical_to_output.items():
            src = fields.get(logical)
            overlay[out_col] = overlay[src] if src else "-"

        group_cols = ["zone_id", "provinsi", "kota_kabupaten", "kecamatan", "desa_kelurahan", "status"]
        agg_spec: dict[str, tuple[str, str]] = {
            "luas_ha": ("luas_ha", "sum"),
            "skor_cpi_rata2": ("skor_cpi_rata2", "mean"),
        }
        for col in overlay.columns:
            if col.startswith("score_") and col.endswith("_rata2"):
                agg_spec[col] = (col, "mean")
        if "slope_percent_rata2" in overlay.columns:
            agg_spec["slope_percent_rata2"] = ("slope_percent_rata2", "mean")

        df = overlay.groupby(group_cols, dropna=False).agg(**agg_spec).reset_index()
        df = df.rename(columns={"status": "status_lahan_kritis"})
    else:
        tmp = class_gdf.copy()
        tmp["zone_id"] = "-"
        for col in ["provinsi", "kota_kabupaten", "kecamatan", "desa_kelurahan"]:
            tmp[col] = "-"
        group_cols = ["zone_id", "provinsi", "kota_kabupaten", "kecamatan", "desa_kelurahan", "status"]
        agg_spec = {
            "luas_ha": ("luas_ha", "sum"),
            "skor_cpi_rata2": ("skor_cpi_rata2", "mean"),
        }
        for col in tmp.columns:
            if col.startswith("score_") and col.endswith("_rata2"):
                agg_spec[col] = (col, "mean")
        if "slope_percent_rata2" in tmp.columns:
            agg_spec["slope_percent_rata2"] = ("slope_percent_rata2", "mean")
        df = tmp.groupby(group_cols, dropna=False).agg(**agg_spec).reset_index()
        df = df.rename(columns={"status": "status_lahan_kritis"})

    numeric_cols = [c for c in df.columns if c.endswith("_rata2") or c == "luas_ha"]
    for col in numeric_cols:
        df[col] = _safe_round_series(df[col], 2)

    base_cols = [
        "zone_id", "provinsi", "kota_kabupaten", "kecamatan", "desa_kelurahan",
        "status_lahan_kritis", "skor_cpi_rata2", "luas_ha",
    ]
    extra_cols = [c for c in df.columns if c not in base_cols]
    df = df[base_cols + extra_cols]
    df["alasan_skor"] = df.apply(_build_reason, axis=1)
    df["rekomendasi_intervensi"] = df["status_lahan_kritis"].map(lambda s: default_recommendation(str(s), cfg))
    return df.sort_values(["status_lahan_kritis", "luas_ha"], ascending=[True, False]).reset_index(drop=True)


def save_outputs(
    out_dir: str | Path,
    class_gdf: gpd.GeoDataFrame,
    summary_df: pd.DataFrame,
    metadata: Dict[str, Any],
) -> Dict[str, str]:
    out = Path(out_dir)
    out.mkdir(parents=True, exist_ok=True)
    paths = {
        "geojson": str(out / "peta_kekritisan_lahan.geojson"),
        "table_csv": str(out / "tabel_lokasi_kritis.csv"),
        "table_json": str(out / "tabel_lokasi_kritis.json"),
        "metadata_json": str(out / "metadata_analisis.json"),
    }
    if not class_gdf.empty:
        class_gdf.to_crs("EPSG:4326").to_file(paths["geojson"], driver="GeoJSON")
    else:
        Path(paths["geojson"]).write_text('{"type":"FeatureCollection","features":[]}', encoding="utf-8")
    summary_df.to_csv(paths["table_csv"], index=False)
    summary_df.to_json(paths["table_json"], orient="records", force_ascii=False, indent=2)
    Path(paths["metadata_json"]).write_text(json.dumps(metadata, ensure_ascii=False, indent=2), encoding="utf-8")
    return paths


def make_leaflet_html(
    geojson_path: str | Path,
    table_df: pd.DataFrame,
    out_html: str | Path,
    title: str = "Peta Kekritisan Lahan CPI-AHP",
) -> str:
    geojson_path = Path(geojson_path)
    out_html = Path(out_html)
    data = json.loads(geojson_path.read_text(encoding="utf-8"))

    if data.get("features"):
        gdf = gpd.read_file(geojson_path)
        centroid = gdf.to_crs(3857).unary_union.centroid
        centroid_wgs = gpd.GeoSeries([centroid], crs=3857).to_crs(4326).iloc[0]
        center = [centroid_wgs.y, centroid_wgs.x]
    else:
        center = [-6.9, 107.6]

    m = folium.Map(location=center, zoom_start=9, tiles="OpenStreetMap")
    folium.TileLayer("CartoDB positron", name="CartoDB Positron").add_to(m)

    def style_fn(feature):
        color = feature["properties"].get("color", "#777777")
        return {"fillColor": color, "color": "#ffffff", "weight": 0.4, "fillOpacity": 0.65}

    if data.get("features"):
        tooltip_fields = [f for f in ["desa_kelurahan", "kecamatan", "kota_kabupaten", "status_lahan_kritis", "status", "skor_cpi_rata2", "luas_ha", "class_id"] if f in data["features"][0].get("properties", {})]
        folium.GeoJson(
            data,
            name="Kekritisan Lahan",
            style_function=style_fn,
            tooltip=folium.GeoJsonTooltip(fields=tooltip_fields) if tooltip_fields else None,
        ).add_to(m)

    legend_html = """
    <div style="position: fixed; bottom: 30px; left: 30px; z-index: 9999; background: white; padding: 12px; border: 1px solid #999; border-radius: 6px; font-size: 13px;">
      <b>Legenda</b><br>
      <span style="display:inline-block;width:14px;height:14px;background:#2E7D32;margin-right:6px;"></span>Tidak Kritis<br>
      <span style="display:inline-block;width:14px;height:14px;background:#FB8C00;margin-right:6px;"></span>Kritis<br>
      <span style="display:inline-block;width:14px;height:14px;background:#C62828;margin-right:6px;"></span>Sangat Kritis
    </div>
    """
    m.get_root().html.add_child(folium.Element(legend_html))

    table_html = table_df.head(100).to_html(index=False, classes="table table-sm", border=0)
    dashboard_html = f"""
    <div style="position: fixed; top: 10px; left: 50px; right: 50px; z-index: 9999; background: rgba(255,255,255,0.95); padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; max-height: 260px; overflow:auto;">
      <h3 style="margin: 0 0 8px 0; font-family: Arial;">{title}</h3>
      <div style="font-size:12px; font-family:Arial; margin-bottom:8px;">Preview 100 baris pertama. File lengkap tersedia melalui endpoint hasil.</div>
      {table_html}
    </div>
    """
    m.get_root().html.add_child(folium.Element(dashboard_html))
    folium.LayerControl().add_to(m)
    m.save(str(out_html))
    return str(out_html)

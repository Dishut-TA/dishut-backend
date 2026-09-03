from __future__ import annotations

import re
from pathlib import Path
from typing import Any, Dict, Iterable, Optional, Tuple

import geopandas as gpd
import numpy as np
from rasterio.enums import Resampling

from .geo import RasterReference, is_raster, is_vector, read_raster_to_reference, read_vector, rasterize_constant_vector, rasterize_value_vector


def normalize_text(value: Any) -> str:
    if value is None:
        return ""
    text = str(value).strip().lower()
    text = re.sub(r"[_\-]+", " ", text)
    text = re.sub(r"\s+", " ", text)
    return text


def pick_field(gdf: gpd.GeoDataFrame, candidates: Iterable[str]) -> Optional[str]:
    existing = {c.lower(): c for c in gdf.columns if c.lower() != "geometry"}
    for cand in candidates:
        if cand.lower() in existing:
            return existing[cand.lower()]
    return None


def score_by_thresholds(values: np.ndarray, thresholds: Iterable[Dict[str, Any]], nodata: float, default_score: float) -> np.ndarray:
    out = np.full(values.shape, default_score, dtype="float32")
    valid = values != nodata
    out[~valid] = nodata
    for t in thresholds:
        lo = float(t["min"])
        hi = float(t["max"])
        score = float(t["score"])
        mask = valid & (values >= lo) & (values < hi)
        out[mask] = score
    return out


def score_by_raster_values(values: np.ndarray, mapping: Dict[Any, Any], nodata: float, default_score: float) -> np.ndarray:
    out = np.full(values.shape, default_score, dtype="float32")
    valid = values != nodata
    out[~valid] = nodata
    # YAML numeric keys may be int, but raster array is float. Compare safely.
    for key, spec in mapping.items():
        try:
            k = float(key)
        except Exception:
            continue
        score = float(spec.get("score", default_score)) if isinstance(spec, dict) else float(spec)
        out[valid & np.isclose(values, k)] = score
    return out


def score_vector_classes(
    path: str | Path,
    ref: RasterReference,
    rule: Dict[str, Any],
    fallback_crs: str | None,
    warnings: list[str],
) -> np.ndarray:
    gdf = read_vector(path, fallback_crs=fallback_crs)
    default_score = float(rule.get("default_score", 3))
    field = pick_field(gdf, rule.get("field_candidates", []))

    if field is None:
        warnings.append(f"{Path(path).name}: tidak ada field atribut yang cocok; seluruh geometri diberi default_score={default_score}.")
        return rasterize_constant_vector(gdf, ref, default_score)

    classes = rule.get("classes", {}) or {}
    raster_values = rule.get("raster_values", {}) or {}
    # Build a temporary numeric score column.
    score_values = []
    for raw in gdf[field].tolist():
        score = default_score
        # Numeric mapping first.
        try:
            as_int = int(float(raw))
            if as_int in raster_values:
                spec = raster_values[as_int]
                score = float(spec.get("score", default_score)) if isinstance(spec, dict) else float(spec)
                score_values.append(score)
                continue
        except Exception:
            pass

        raw_norm = normalize_text(raw)
        if raw_norm in classes:
            spec = classes[raw_norm]
            score = float(spec.get("score", default_score)) if isinstance(spec, dict) else float(spec)
        else:
            # Partial match fallback. Example: "Calcaric Fluvisols" contains "fluvisols".
            for key, spec in classes.items():
                if normalize_text(key) in raw_norm:
                    score = float(spec.get("score", default_score)) if isinstance(spec, dict) else float(spec)
                    break
        score_values.append(score)

    score_field = "__score__"
    gdf = gdf.copy()
    gdf[score_field] = score_values
    return rasterize_value_vector(gdf, ref, score_field, default_score)


def score_indicator(
    name: str,
    path: str | Path,
    ref: RasterReference,
    cfg: Dict[str, Any],
    fallback_crs: str | None,
    warnings: list[str],
) -> np.ndarray:
    rules = cfg["rules"]
    if name not in rules:
        raise KeyError(f"No scoring rule found for indicator: {name}")
    rule = rules[name]
    default_score = float(rule.get("default_score", 3))

    if is_raster(path):
        # Categorical rasters should use nearest. Continuous rasters use bilinear.
        resampling = Resampling.nearest if name in {"landcover", "soil"} or rule.get("mode") == "score_raster" else Resampling.bilinear
        values = read_raster_to_reference(path, ref, resampling=resampling)
        if name == "rainfall" and rule.get("mode") == "threshold_mm":
            return score_by_thresholds(values, rule.get("thresholds", []), ref.nodata, default_score)
        if name == "rainfall" and rule.get("mode") == "score_raster":
            out = values.astype("float32")
            valid = out != ref.nodata
            out[valid] = np.clip(out[valid], 1, 5)
            return out
        if rule.get("thresholds"):
            return score_by_thresholds(values, rule.get("thresholds", []), ref.nodata, default_score)
        if rule.get("raster_values"):
            return score_by_raster_values(values, rule.get("raster_values", {}), ref.nodata, default_score)
        warnings.append(f"{Path(path).name}: raster tidak punya thresholds/raster_values; dipakai nilai piksel sebagai skor 1..5.")
        out = values.astype("float32")
        valid = out != ref.nodata
        out[valid] = np.clip(out[valid], 1, 5)
        return out

    if is_vector(path):
        return score_vector_classes(path, ref, rule, fallback_crs, warnings)

    raise ValueError(f"Unsupported indicator format for {name}: {path}")


def score_slope_from_dem(dem_array: np.ndarray, ref: RasterReference, slope_rule: Dict[str, Any]) -> Tuple[np.ndarray, np.ndarray]:
    """Return slope percent array and scored slope array.

    DEM must already be in projected CRS with meter-like units for meaningful slope.
    """
    nodata = ref.nodata
    dem = dem_array.astype("float32")
    valid = dem != nodata
    # Fill nodata by nearest safe neutral value for gradient, then restore mask.
    fill_value = float(np.nanmedian(dem[valid])) if np.any(valid) else 0.0
    dem_filled = dem.copy()
    dem_filled[~valid] = fill_value

    xres = abs(float(ref.transform.a))
    yres = abs(float(ref.transform.e))
    gy, gx = np.gradient(dem_filled, yres, xres)
    slope_rad = np.arctan(np.sqrt(gx ** 2 + gy ** 2))
    slope_percent = np.tan(slope_rad) * 100.0
    slope_percent = slope_percent.astype("float32")
    slope_percent[~valid] = nodata
    scored = score_by_thresholds(
        slope_percent,
        slope_rule.get("thresholds", []),
        nodata=nodata,
        default_score=float(slope_rule.get("default_score", 3)),
    )
    return slope_percent, scored


def normalize_score(score_arr: np.ndarray, nodata: float) -> np.ndarray:
    """Normalize 1..5 score to 0..100; nodata preserved."""
    out = np.full(score_arr.shape, nodata, dtype="float32")
    valid = score_arr != nodata
    out[valid] = ((score_arr[valid] - 1.0) / 4.0) * 100.0
    out[valid] = np.clip(out[valid], 0, 100)
    return out

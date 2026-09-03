from __future__ import annotations

from dataclasses import asdict, dataclass
from pathlib import Path
from typing import Any, Dict, Optional

import numpy as np
from rasterio.enums import Resampling

from .ahp import calculate_ahp, filter_and_renormalize_weights
from .config import get_project_nodata, load_config
from .cpi import classify_cpi, weighted_cpi
from .geo import (
    make_reference_from_aoi,
    mask_array_by_aoi,
    read_raster_to_reference,
    read_vector,
    save_geotiff,
)
from .outputs import build_admin_zonal_gdf, build_summary_table, make_leaflet_html, polygonize_classes, save_outputs
from .intervention import apply_intervention_rules
from .scoring import normalize_score, score_indicator, score_slope_from_dem


@dataclass
class CPIEngineResult:
    output_dir: str
    paths: Dict[str, str]
    ahp: Dict[str, Any]
    warnings: list[str]


class CPIAHPEngine:
    def __init__(self, config_path: str | Path):
        self.config_path = Path(config_path)
        self.cfg = load_config(self.config_path)
        self.warnings: list[str] = []

    def run(
        self,
        *,
        dem_path: str | Path,
        rainfall_path: str | Path,
        soil_path: str | Path,
        aoi_path: str | Path,
        out_dir: str | Path,
        landcover_path: str | Path | None = None,
        admin_path: str | Path | None = None,
        allow_missing_indicators: bool = False,
        target_resolution_override: float | None = None,
        save_intermediate_rasters: bool = False,
    ) -> CPIEngineResult:
        out_dir = Path(out_dir)
        out_dir.mkdir(parents=True, exist_ok=True)
        project = self.cfg.get("project", {})
        nodata = get_project_nodata(self.cfg)
        target_crs = project.get("target_crs", "EPSG:32748")
        target_resolution = float(target_resolution_override or project.get("target_resolution", 30))
        fallback_crs = project.get("fallback_vector_crs", "EPSG:4326")

        # 1. AOI + reference grid.
        aoi = read_vector(aoi_path, fallback_crs=fallback_crs)
        ref = make_reference_from_aoi(aoi, target_crs=target_crs, resolution=target_resolution, nodata=nodata)

        # 2. DEM -> slope.
        dem = read_raster_to_reference(dem_path, ref, resampling=Resampling.bilinear)
        dem = mask_array_by_aoi(dem, ref, aoi)
        slope_percent, slope_score = score_slope_from_dem(dem, ref, self.cfg["rules"]["slope"])

        # 3. Other indicator scores.
        indicator_paths: Dict[str, str | Path | None] = {
            "landcover": landcover_path,
            "rainfall": rainfall_path,
            "soil": soil_path,
            "slope": "__derived_from_dem__",
        }
        indicator_scores: Dict[str, np.ndarray] = {"slope": slope_score}

        for name in ["landcover", "rainfall", "soil"]:
            path = indicator_paths[name]
            if path is None:
                if allow_missing_indicators:
                    self.warnings.append(f"Indikator {name} tidak diinput; bobot AHP akan dinormalisasi ulang ke indikator tersedia.")
                    continue
                raise ValueError(f"Missing required indicator: {name}. Use allow_missing_indicators=True if this is intentional.")
            arr = score_indicator(name, path, ref, self.cfg, fallback_crs=fallback_crs, warnings=self.warnings)
            arr = mask_array_by_aoi(arr, ref, aoi)
            indicator_scores[name] = arr

        # 4. Normalize scores.
        normalized = {name: normalize_score(arr, nodata) for name, arr in indicator_scores.items()}

        # 5. AHP weights.
        ahp_cfg = self.cfg["ahp"]
        ahp = calculate_ahp(
            indicators=ahp_cfg["indicators"],
            pairwise_matrix=ahp_cfg["pairwise_matrix"],
            consistency_threshold=float(ahp_cfg.get("consistency_threshold", 0.1)),
        )
        if not ahp.is_consistent:
            msg = f"AHP tidak konsisten: CR={ahp.cr:.4f} > threshold={ahp_cfg.get('consistency_threshold', 0.1)}."
            if ahp_cfg.get("fail_if_inconsistent", False):
                raise ValueError(msg)
            self.warnings.append(msg)
        weights = filter_and_renormalize_weights(ahp.weights, normalized.keys())

        # 6. CPI + classification.
        cpi_arr = weighted_cpi(normalized, weights, nodata)
        class_arr = classify_cpi(cpi_arr, self.cfg["classification"]["thresholds"], nodata)

        # 7. Save rasters.
        raster_paths = {
            "cpi_score": str(out_dir / "cpi_score.tif"),
            "class_raster": str(out_dir / "class_kekritisan.tif"),
        }

        # Output utama tetap disimpan. Fungsi save_geotiff sudah low-memory.
        save_geotiff(raster_paths["cpi_score"], cpi_arr, ref, dtype="float32")
        save_geotiff(raster_paths["class_raster"], class_arr, ref, dtype="uint8", nodata=0)

        # Raster antara hanya disimpan kalau diminta. Ini mengurangi waktu, ukuran output,
        # dan risiko error RAM pada laptop Windows.
        if save_intermediate_rasters:
            intermediate_paths = {
                "dem_matched": str(out_dir / "dem_matched.tif"),
                "slope_percent": str(out_dir / "slope_percent.tif"),
            }
            save_geotiff(intermediate_paths["dem_matched"], dem, ref, dtype="float32")
            save_geotiff(intermediate_paths["slope_percent"], slope_percent, ref, dtype="float32")
            raster_paths.update(intermediate_paths)

            for name, arr in indicator_scores.items():
                save_geotiff(out_dir / f"score_{name}.tif", arr, ref, dtype="float32")
            for name, arr in normalized.items():
                save_geotiff(out_dir / f"norm_{name}.tif", arr, ref, dtype="float32")

        # 8. Build map + summary table.
        # If admin_path exists, do not polygonize raster first and do not run
        # build_summary_table(admin_path=...). That route is very slow for
        # thousands of village polygons. Build one fast admin-zonal GeoDataFrame
        # and derive the table directly from it.
        if admin_path:
            zonal_gdf = build_admin_zonal_gdf(
                cpi_arr,
                ref,
                self.cfg,
                admin_path=admin_path,
                aoi_path=aoi_path,
                fallback_crs=fallback_crs,
                indicator_scores=indicator_scores,
                slope_percent=slope_percent,
            )
            if not zonal_gdf.empty:
                map_gdf = zonal_gdf
                summary_df = zonal_gdf.drop(columns=["geometry"], errors="ignore").copy()
            else:
                class_gdf = polygonize_classes(
                    class_arr,
                    ref,
                    self.cfg["classification"]["thresholds"],
                    simplify_tolerance=float(project.get("output_simplify_tolerance", 0) or 0),
                )
                map_gdf = class_gdf
                summary_df = build_summary_table(
                    class_gdf,
                    cpi_arr,
                    ref,
                    self.cfg,
                    admin_path=None,
                    aoi_path=aoi_path,
                    fallback_crs=fallback_crs,
                    indicator_scores=indicator_scores,
                    slope_percent=slope_percent,
                )
        else:
            class_gdf = polygonize_classes(
                class_arr,
                ref,
                self.cfg["classification"]["thresholds"],
                simplify_tolerance=float(project.get("output_simplify_tolerance", 0) or 0),
            )
            map_gdf = class_gdf
            summary_df = build_summary_table(
                class_gdf,
                cpi_arr,
                ref,
                self.cfg,
                admin_path=None,
                aoi_path=aoi_path,
                fallback_crs=fallback_crs,
                indicator_scores=indicator_scores,
                slope_percent=slope_percent,
            )

        summary_df = apply_intervention_rules(summary_df, self.cfg)

        # 9. Metadata + web map.
        metadata = {
            "engine": "CPI-AHP Lahan Kritis",
            "config_path": str(self.config_path),
            "target_crs": target_crs,
            "target_resolution": target_resolution,
            "nodata": nodata,
            "ahp": {
                "weights_original": ahp.weights,
                "weights_used": weights,
                "lambda_max": ahp.lambda_max,
                "ci": ahp.ci,
                "ri": ahp.ri,
                "cr": ahp.cr,
                "is_consistent": ahp.is_consistent,
            },
            "inputs": {
                "dem": str(dem_path),
                "rainfall": str(rainfall_path),
                "soil": str(soil_path),
                "landcover": str(landcover_path) if landcover_path else None,
                "aoi": str(aoi_path),
                "admin": str(admin_path) if admin_path else None,
            },
            "warnings": self.warnings,
        }
        paths = save_outputs(out_dir, map_gdf, summary_df, metadata)
        paths.update(raster_paths)
        paths["map_html"] = make_leaflet_html(paths["geojson"], summary_df, out_dir / "peta_kekritisan_lahan.html")

        return CPIEngineResult(
            output_dir=str(out_dir),
            paths=paths,
            ahp=metadata["ahp"],
            warnings=self.warnings,
        )

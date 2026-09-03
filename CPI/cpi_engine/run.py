from __future__ import annotations

import argparse
import json
from dataclasses import asdict
from pathlib import Path

from .engine import CPIAHPEngine


def build_parser() -> argparse.ArgumentParser:
    p = argparse.ArgumentParser(description="Run CPI-AHP land criticality engine.")
    p.add_argument("--config", required=True, help="Path to rules.yaml")
    p.add_argument("--dem", required=True, help="DEM raster .tif")
    p.add_argument("--rainfall", required=True, help="Rainfall raster .tif or scored raster .tif")
    p.add_argument("--soil", required=True, help="Soil raster/vector path")
    p.add_argument("--aoi", required=True, help="DAS/AOI vector path")
    p.add_argument("--landcover", default=None, help="Landcover raster/vector path")
    p.add_argument("--admin", default=None, help="Administrative boundary vector path for table")
    p.add_argument("--out", required=True, help="Output directory")
    p.add_argument("--allow-missing-indicators", action="store_true", help="Allow missing landcover and renormalize AHP weights")
    p.add_argument("--target-resolution",type=float,default=None, help="Override raster resolution in meters. Use 100 or 250 for low-memory demo runs.",)
    p.add_argument(
    "--save-intermediate-rasters",
    action="store_true",
    help=(
        "Save intermediate rasters such as DEM matched, slope, score_*, and norm_*. "
        "Disable for low-memory runs."
    ),
    )
    return p


def main() -> None:
    args = build_parser().parse_args()
    engine = CPIAHPEngine(args.config)
    result = engine.run(
        dem_path=args.dem,
        rainfall_path=args.rainfall,
        soil_path=args.soil,
        landcover_path=args.landcover,
        aoi_path=args.aoi,
        admin_path=args.admin,
        out_dir=args.out,
        allow_missing_indicators=args.allow_missing_indicators,
        target_resolution_override=args.target_resolution,
        save_intermediate_rasters=args.save_intermediate_rasters,
    )
    print(json.dumps(asdict(result), ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()

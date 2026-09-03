from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
from typing import Any, Dict, Iterable, List, Optional, Tuple

import geopandas as gpd
import numpy as np
import rasterio
from rasterio import features
from rasterio.enums import Resampling
from rasterio.io import DatasetReader
from rasterio.mask import mask
from rasterio.transform import Affine, from_bounds
from rasterio.windows import Window
from rasterio.vrt import WarpedVRT
from shapely.geometry import mapping
from shapely.ops import unary_union


VECTOR_EXTS = {".shp", ".geojson", ".json", ".gpkg"}
RASTER_EXTS = {".tif", ".tiff"}


@dataclass(frozen=True)
class RasterReference:
    crs: Any
    transform: Affine
    width: int
    height: int
    nodata: float

    @property
    def shape(self) -> Tuple[int, int]:
        return (self.height, self.width)


def is_raster(path: str | Path) -> bool:
    return Path(path).suffix.lower() in RASTER_EXTS


def is_vector(path: str | Path) -> bool:
    return Path(path).suffix.lower() in VECTOR_EXTS


def read_vector(path: str | Path, fallback_crs: str | None = None) -> gpd.GeoDataFrame:
    path = Path(path)
    if not path.exists():
        raise FileNotFoundError(f"Vector file not found: {path}")
    gdf = gpd.read_file(path)
    if gdf.empty:
        raise ValueError(f"Vector file is empty: {path}")
    gdf = gdf[gdf.geometry.notnull()].copy()
    if gdf.empty:
        raise ValueError(f"Vector file has no valid geometry: {path}")
    # Drop Z to avoid rasterization / overlay problems.
    try:
        gdf["geometry"] = gdf.geometry.force_2d()
    except Exception:
        pass
    if gdf.crs is None:
        if fallback_crs is None:
            raise ValueError(f"Vector CRS missing and no fallback_crs provided: {path}")
        gdf = gdf.set_crs(fallback_crs, allow_override=True)
    return gdf


def dissolve_aoi(aoi: gpd.GeoDataFrame, target_crs: str) -> gpd.GeoDataFrame:
    aoi2 = aoi.to_crs(target_crs)
    geom = unary_union(aoi2.geometry)
    return gpd.GeoDataFrame({"aoi_id": [1]}, geometry=[geom], crs=target_crs)


def make_reference_from_aoi(aoi: gpd.GeoDataFrame, target_crs: str, resolution: float, nodata: float) -> RasterReference:
    aoi_t = aoi.to_crs(target_crs)
    minx, miny, maxx, maxy = aoi_t.total_bounds
    if not np.all(np.isfinite([minx, miny, maxx, maxy])):
        raise ValueError("AOI bounds are invalid.")
    width = int(np.ceil((maxx - minx) / resolution))
    height = int(np.ceil((maxy - miny) / resolution))
    if width <= 0 or height <= 0:
        raise ValueError("AOI reference raster has non-positive dimensions.")
    transform = from_bounds(minx, miny, minx + width * resolution, miny + height * resolution, width, height)
    return RasterReference(crs=target_crs, transform=transform, width=width, height=height, nodata=nodata)


def read_raster_to_reference(
    raster_path: str | Path,
    ref: RasterReference,
    resampling: Resampling = Resampling.bilinear,
) -> np.ndarray:
    raster_path = Path(raster_path)
    if not raster_path.exists():
        raise FileNotFoundError(f"Raster file not found: {raster_path}")
    with rasterio.open(raster_path) as src:
        src_nodata = src.nodata
        with WarpedVRT(
            src,
            crs=ref.crs,
            transform=ref.transform,
            width=ref.width,
            height=ref.height,
            resampling=resampling,
            nodata=ref.nodata,
        ) as vrt:
            arr = vrt.read(1, out_dtype="float32", masked=False)
    arr = arr.astype("float32")
    # Convert possible source nodata values if WarpedVRT missed them.
    if src_nodata is not None:
        arr[np.isclose(arr, src_nodata)] = ref.nodata
    return arr


def mask_array_by_aoi(arr: np.ndarray, ref: RasterReference, aoi: gpd.GeoDataFrame) -> np.ndarray:
    aoi_t = aoi.to_crs(ref.crs)
    mask_arr = features.rasterize(
        [(geom, 1) for geom in aoi_t.geometry],
        out_shape=ref.shape,
        transform=ref.transform,
        fill=0,
        dtype="uint8",
        all_touched=True,
    )
    out = arr.copy()
    out[mask_arr == 0] = ref.nodata
    return out


def rasterize_constant_vector(gdf: gpd.GeoDataFrame, ref: RasterReference, default_value: float) -> np.ndarray:
    gdf_t = gdf.to_crs(ref.crs)
    shapes = [(geom, default_value) for geom in gdf_t.geometry if geom is not None and not geom.is_empty]
    arr = features.rasterize(
        shapes,
        out_shape=ref.shape,
        transform=ref.transform,
        fill=ref.nodata,
        dtype="float32",
        all_touched=True,
    )
    return arr.astype("float32")


def rasterize_value_vector(gdf: gpd.GeoDataFrame, ref: RasterReference, value_field: str, default_value: float) -> np.ndarray:
    gdf_t = gdf.to_crs(ref.crs)
    values = []
    for _, row in gdf_t.iterrows():
        geom = row.geometry
        if geom is None or geom.is_empty:
            continue
        val = row.get(value_field, default_value)
        try:
            val = float(val)
        except Exception:
            val = default_value
        values.append((geom, val))
    return features.rasterize(
        values,
        out_shape=ref.shape,
        transform=ref.transform,
        fill=ref.nodata,
        dtype="float32",
        all_touched=True,
    ).astype("float32")


def save_geotiff(
    path: str | Path,
    arr: np.ndarray,
    ref: RasterReference,
    dtype: str = "float32",
    nodata: float | None = None,
    block_rows: int = 256,
) -> None:
    """
    Simpan array ke GeoTIFF dengan mode low-memory.

    Jangan gunakan dst.write(arr.astype(dtype), 1) untuk raster besar, karena
    Rasterio/NumPy bisa membuat salinan array berbentuk (1, height, width).
    Untuk raster 7436 x 9140 float32, salinan itu sekitar 259 MiB dan bisa
    memicu MemoryError di Windows.

    Fungsi ini menulis per window/baris sehingga hanya blok kecil yang
    dikonversi ke dtype target.
    """
    path = Path(path)
    path.parent.mkdir(parents=True, exist_ok=True)

    if arr.shape != ref.shape:
        raise ValueError(
            f"Array shape {arr.shape} tidak sama dengan reference shape {ref.shape}."
        )

    nd = ref.nodata if nodata is None else nodata
    dtype_np = np.dtype(dtype)
    block_rows = max(1, int(block_rows or 256))

    profile = {
        "driver": "GTiff",
        "height": ref.height,
        "width": ref.width,
        "count": 1,
        "dtype": dtype_np.name,
        "crs": ref.crs,
        "transform": ref.transform,
        "nodata": nd,
        "compress": "deflate",
        "predictor": 2 if dtype_np.kind == "f" else 1,
        "BIGTIFF": "IF_SAFER",
        "tiled": True,
        "blockxsize": 256,
        "blockysize": 256,
    }

    with rasterio.open(path, "w", **profile) as dst:
        for row0 in range(0, ref.height, block_rows):
            row1 = min(row0 + block_rows, ref.height)
            window = Window(0, row0, ref.width, row1 - row0)

            block = arr[row0:row1, :]
            if block.dtype != dtype_np:
                block = block.astype(dtype_np, copy=False)

            dst.write(block, 1, window=window)


def pixel_area_hectare(ref: RasterReference) -> float:
    return abs(ref.transform.a * ref.transform.e) / 10000.0

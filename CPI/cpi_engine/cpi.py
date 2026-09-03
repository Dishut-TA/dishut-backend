from __future__ import annotations

from typing import Any, Dict, Mapping

import numpy as np


def weighted_cpi(
    normalized_scores: Mapping[str, np.ndarray],
    weights: Mapping[str, float],
    nodata: float,
    block_rows: int = 256,
) -> np.ndarray:
    """Memory-safe weighted CPI.

    Older version multiplied a full 7k x 9k raster in one shot. On Windows laptops
    this can fail because NumPy creates a temporary float32 array of hundreds of MB.
    This version processes rows in blocks, so temporary arrays stay small.
    """
    if not normalized_scores:
        raise ValueError("No normalized scores supplied.")

    first = next(iter(normalized_scores.values()))
    h, w = first.shape
    out = np.full((h, w), nodata, dtype="float32")

    used = [name for name in normalized_scores.keys() if name in weights]
    if not used:
        raise ValueError("No supplied indicator has a matching AHP weight.")

    block_rows = max(1, int(block_rows or 256))

    for row0 in range(0, h, block_rows):
        row1 = min(row0 + block_rows, h)
        block_shape = (row1 - row0, w)
        out_block = np.zeros(block_shape, dtype="float32")
        valid_all = np.ones(block_shape, dtype=bool)

        for name in used:
            arr_block = normalized_scores[name][row0:row1]
            valid = arr_block != nodata
            valid_all &= valid

            # Small temporary block only, not a full-raster copy.
            tmp = np.empty(block_shape, dtype="float32")
            np.multiply(arr_block, float(weights[name]), out=tmp, casting="unsafe")
            tmp[~valid] = 0.0
            out_block += tmp

        np.clip(out_block, 0, 100, out=out_block)
        out_block[~valid_all] = nodata
        out[row0:row1] = out_block

    return out


def classify_cpi(cpi_arr: np.ndarray, thresholds: Dict[str, Any], nodata: float, block_rows: int = 512) -> np.ndarray:
    """Classify CPI into uint8 classes using row blocks to reduce temporary masks."""
    h, w = cpi_arr.shape
    out = np.zeros((h, w), dtype="uint8")
    block_rows = max(1, int(block_rows or 512))

    for row0 in range(0, h, block_rows):
        row1 = min(row0 + block_rows, h)
        block = cpi_arr[row0:row1]
        valid = block != nodata
        out_block = out[row0:row1]
        for _, spec in thresholds.items():
            lo = float(spec["min"])
            hi = float(spec["max"])
            class_id = int(spec["class_id"])
            mask = valid & (block >= lo) & (block < hi)
            out_block[mask] = class_id
    return out


def class_lookup(thresholds: Dict[str, Any]) -> Dict[int, Dict[str, Any]]:
    return {int(v["class_id"]): {**v, "key": k} for k, v in thresholds.items()}

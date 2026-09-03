from __future__ import annotations

from typing import Any, Dict, Iterable, Mapping

import numpy as np
import pandas as pd


def _num(row: pd.Series, key: str, default: float = float("nan")) -> float:
    try:
        value = float(row.get(key, default))
        return value
    except Exception:
        return default


def _contains_history(history: Iterable[str], keyword: str) -> bool:
    k = keyword.lower()
    return any(k in str(item).lower() for item in history or [])


def apply_intervention_rules(
    summary_df: pd.DataFrame,
    cfg: Dict[str, Any],
    histories: Mapping[str, list[str]] | None = None,
) -> pd.DataFrame:
    """Add historical intervention and rule-based recommendation columns.

    histories maps zone_id to a list such as ["Reboisasi", "Agroforestri"].
    The function keeps the table usable even when no PHP historical API is supplied.
    """
    histories = histories or {}
    if summary_df.empty:
        return summary_df

    df = summary_df.copy()
    recommendations = []
    history_col = []

    for _, row in df.iterrows():
        zone_id = str(row.get("zone_id", "-") or "-")
        history = histories.get(zone_id, []) or histories.get(str(zone_id), []) or []
        history_col.append(", ".join(map(str, history)) if history else "-")
        recommendations.append(recommend_one(row, cfg, history))

    df["riwayat_intervensi"] = history_col
    df["rekomendasi_intervensi"] = recommendations
    return df


def recommend_one(row: pd.Series, cfg: Dict[str, Any], history: Iterable[str] | None = None) -> str:
    history = list(history or [])
    status = str(row.get("status_lahan_kritis", ""))
    slope_score = _num(row, "score_slope_rata2")
    slope_percent = _num(row, "slope_percent_rata2")
    land_score = _num(row, "score_landcover_rata2")
    rain_score = _num(row, "score_rainfall_rata2")
    soil_score = _num(row, "score_soil_rata2")

    # Historical intervention modifies the recommendation. This makes the system use master data,
    # not only current raster indicators.
    has_reboisasi = _contains_history(history, "reboisasi")
    has_agro = _contains_history(history, "agro")
    has_teras = _contains_history(history, "teras")

    if status == "Sangat Kritis":
        if has_reboisasi:
            return "Evaluasi keberhasilan reboisasi sebelumnya, lakukan penyulaman tanaman, pengayaan vegetasi, dan pengendalian erosi pada titik prioritas."
        if np.isfinite(slope_percent) and slope_percent >= 25 or np.isfinite(slope_score) and slope_score >= 4:
            if has_teras:
                return "Perkuat terasering yang sudah ada, tambah bangunan pengendali erosi, dan lakukan rehabilitasi vegetatif intensif."
            return "Terasering, bangunan pengendali erosi, reboisasi, dan rehabilitasi vegetatif intensif."
        if np.isfinite(land_score) and land_score >= 4:
            return "Reboisasi atau agroforestri pada lahan terbuka, disertai pembatasan perubahan tutupan lahan dan pengendalian erosi."
        if np.isfinite(rain_score) and rain_score >= 4:
            return "Rehabilitasi kawasan DAS, konservasi vegetatif, dan sistem drainase konservasi pada area curah hujan tinggi."
        return cfg.get("recommendations", {}).get("sangat_kritis", {}).get("default", "Rehabilitasi prioritas dan pengendalian erosi.")

    if status == "Kritis":
        if has_agro:
            return "Lanjutkan agroforestri, tambahkan tanaman penutup tanah, dan lakukan monitoring perubahan tutupan lahan."
        if np.isfinite(land_score) and land_score >= 4:
            return "Agroforestri, penghijauan, tanaman penutup tanah, dan pembatasan aktivitas yang membuka lahan."
        if np.isfinite(slope_score) and slope_score >= 4:
            return "Konservasi tanah pada lereng, teras gulud, saluran pembuangan air, dan penguatan vegetasi."
        if np.isfinite(rain_score) and rain_score >= 4:
            return "Konservasi vegetatif, penguatan drainase konservasi, dan pemeliharaan tutupan lahan."
        return cfg.get("recommendations", {}).get("kritis", {}).get("default", "Konservasi vegetatif dan rehabilitasi ringan-sedang.")

    if status == "Tidak Kritis":
        return cfg.get("recommendations", {}).get("tidak_kritis", {}).get("default", "Pemeliharaan tutupan vegetasi dan monitoring berkala.")

    return "Perlu validasi lapangan sebelum menentukan intervensi."

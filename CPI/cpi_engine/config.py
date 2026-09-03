from __future__ import annotations

from pathlib import Path
from typing import Any, Dict

import yaml


class ConfigError(ValueError):
    pass


def load_config(path: str | Path) -> Dict[str, Any]:
    path = Path(path)
    if not path.exists():
        raise ConfigError(f"Config file not found: {path}")
    with path.open("r", encoding="utf-8") as f:
        cfg = yaml.safe_load(f)
    if not isinstance(cfg, dict):
        raise ConfigError("Config YAML must be a dictionary/object.")
    validate_config(cfg)
    return cfg


def validate_config(cfg: Dict[str, Any]) -> None:
    required = ["project", "ahp", "classification", "rules"]
    for key in required:
        if key not in cfg:
            raise ConfigError(f"Missing required config section: {key}")

    ahp = cfg["ahp"]
    indicators = ahp.get("indicators")
    matrix = ahp.get("pairwise_matrix")
    if not indicators or not matrix:
        raise ConfigError("AHP config requires indicators and pairwise_matrix.")
    if len(matrix) != len(indicators):
        raise ConfigError("AHP pairwise_matrix row count must equal indicator count.")
    for row in matrix:
        if len(row) != len(indicators):
            raise ConfigError("AHP pairwise_matrix must be square.")

    thresholds = cfg["classification"].get("thresholds")
    if not thresholds:
        raise ConfigError("classification.thresholds cannot be empty.")
    for name, t in thresholds.items():
        for k in ["min", "max", "label", "color", "class_id"]:
            if k not in t:
                raise ConfigError(f"classification.thresholds.{name} missing {k}")


def get_project_nodata(cfg: Dict[str, Any]) -> float:
    return float(cfg.get("project", {}).get("nodata", -9999))

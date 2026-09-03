from __future__ import annotations

from dataclasses import dataclass
from typing import Dict, Iterable, List

import numpy as np


RI_TABLE = {
    1: 0.00,
    2: 0.00,
    3: 0.58,
    4: 0.90,
    5: 1.12,
    6: 1.24,
    7: 1.32,
    8: 1.41,
    9: 1.45,
    10: 1.49,
}


@dataclass(frozen=True)
class AHPResult:
    indicators: List[str]
    weights: Dict[str, float]
    lambda_max: float
    ci: float
    ri: float
    cr: float
    is_consistent: bool


def calculate_ahp(
    indicators: Iterable[str],
    pairwise_matrix: Iterable[Iterable[float]],
    consistency_threshold: float = 0.10,
) -> AHPResult:
    """Calculate AHP weights using eigenvector method.

    Args:
        indicators: names in the same order as matrix rows/columns.
        pairwise_matrix: Saaty reciprocal comparison matrix.
        consistency_threshold: usually 0.10.
    """
    names = list(indicators)
    matrix = np.array(pairwise_matrix, dtype=float)
    n = len(names)

    if matrix.shape != (n, n):
        raise ValueError("Pairwise matrix size must equal number of indicators.")
    if np.any(matrix <= 0):
        raise ValueError("AHP matrix values must be positive.")

    # Light reciprocal validation. It should not be too strict because users often
    # provide rounded values such as 0.333333.
    reciprocal_error = np.abs(matrix * matrix.T - 1)
    if np.nanmax(reciprocal_error) > 1e-3:
        raise ValueError("AHP matrix must be reciprocal: a_ij should equal 1/a_ji.")

    eigenvalues, eigenvectors = np.linalg.eig(matrix)
    max_idx = int(np.argmax(eigenvalues.real))
    lambda_max = float(eigenvalues[max_idx].real)
    principal_vector = np.abs(eigenvectors[:, max_idx].real)
    weights_arr = principal_vector / principal_vector.sum()

    ci = float((lambda_max - n) / (n - 1)) if n > 2 else 0.0
    ri = float(RI_TABLE.get(n, 1.49))
    cr = float(ci / ri) if ri != 0 else 0.0
    weights = {name: float(w) for name, w in zip(names, weights_arr)}
    return AHPResult(
        indicators=names,
        weights=weights,
        lambda_max=lambda_max,
        ci=ci,
        ri=ri,
        cr=cr,
        is_consistent=cr <= consistency_threshold,
    )


def filter_and_renormalize_weights(weights: Dict[str, float], available_indicators: Iterable[str]) -> Dict[str, float]:
    available = list(available_indicators)
    filtered = {k: float(weights[k]) for k in available if k in weights}
    total = sum(filtered.values())
    if total <= 0:
        raise ValueError("No available indicator weights to normalize.")
    return {k: v / total for k, v in filtered.items()}

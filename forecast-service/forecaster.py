"""
Núcleo de forecasting de Rikuy.

Toma una serie mensual (monto facturado por mes) y proyecta los próximos
periodos con intervalo de confianza. Estrategia adaptativa según el largo de la
historia:

  - >= 24 meses  → ETS con tendencia + estacionalidad anual (statsmodels).
  - 3..23 meses  → ETS con tendencia amortiguada (sin estacionalidad).
  - < 3 meses    → fallback naive (deriva + banda por desviación).

Las ventas no son negativas: la banda inferior se recorta a 0.
"""
from __future__ import annotations

import warnings
from dataclasses import asdict, dataclass

import numpy as np
import pandas as pd

# 2 ciclos anuales completos para que la estacionalidad mensual sea fiable.
SEASONAL_MIN_MONTHS = 24

# Cuantiles normales para la banda del fallback (sin scipy).
_Z = {0.80: 1.2816, 0.85: 1.4395, 0.90: 1.6449, 0.95: 1.9600, 0.99: 2.5758}


@dataclass
class ForecastPoint:
    ds: str       # periodo proyectado, YYYY-MM
    yhat: float   # valor esperado
    yhat_lower: float
    yhat_upper: float


def _to_series(history: list[dict]) -> pd.Series:
    """Convierte [{ds, y}] en una serie indexada por periodo mensual, ordenada."""
    periods = pd.PeriodIndex([h["ds"] for h in history], freq="M")
    values = np.asarray([float(h["y"]) for h in history], dtype="float64")
    return pd.Series(values, index=periods).sort_index()


def _future_labels(last: pd.Period, n: int) -> list[str]:
    return [str(last + i) for i in range(1, n + 1)]


def _z(confidence: float) -> float:
    key = min(_Z, key=lambda c: abs(c - confidence))
    return _Z[key]


def _clamp_lower(value: float) -> float:
    return max(0.0, float(value))


def forecast_series(
    history: list[dict],
    periods: int = 3,
    confidence: float = 0.80,
) -> tuple[list[ForecastPoint], str]:
    """
    Devuelve (puntos_proyectados, nombre_del_modelo). No lanza si el ajuste
    estadístico falla: cae al fallback naive para no romper el dashboard.
    """
    series = _to_series(history)
    n = len(series)
    last = series.index[-1]
    labels = _future_labels(last, periods)

    if n < 3:
        return _naive(series, labels, confidence), "naive"

    seasonal = n >= SEASONAL_MIN_MONTHS
    try:
        return _ets(series, labels, periods, confidence, seasonal), (
            "ets-seasonal" if seasonal else "ets-trend"
        )
    except Exception:  # noqa: BLE001 — cualquier fallo del ajuste → fallback
        return _naive(series, labels, confidence), "naive"


def _ets(
    series: pd.Series,
    labels: list[str],
    periods: int,
    confidence: float,
    seasonal: bool,
) -> list[ForecastPoint]:
    from statsmodels.tsa.exponential_smoothing.ets import ETSModel

    n = len(series)
    alpha = 1.0 - confidence

    # statsmodels exige una Series indexada (con ndarray, get_prediction rompe):
    # usamos un índice mensual de timestamps con frecuencia explícita.
    dated = pd.Series(
        series.to_numpy(),
        index=series.index.to_timestamp(how="start"),
    )
    dated.index.freq = "MS"

    with warnings.catch_warnings():
        warnings.simplefilter("ignore")
        model = ETSModel(
            dated,
            error="add",
            trend="add",
            damped_trend=True,
            seasonal="add" if seasonal else None,
            seasonal_periods=12 if seasonal else None,
        )
        fit = model.fit(disp=False)
        pred = fit.get_prediction(start=n, end=n + periods - 1)
        frame = pred.summary_frame(alpha=alpha)

    mean = np.asarray(frame["mean"])
    lower = np.asarray(frame["pi_lower"])
    upper = np.asarray(frame["pi_upper"])

    if not np.all(np.isfinite(mean)):
        raise ValueError("ETS produjo valores no finitos")

    return [
        ForecastPoint(
            ds=labels[i],
            yhat=round(float(mean[i]), 2),
            yhat_lower=round(_clamp_lower(lower[i]), 2),
            yhat_upper=round(float(upper[i]), 2),
        )
        for i in range(periods)
    ]


def _naive(series: pd.Series, labels: list[str], confidence: float) -> list[ForecastPoint]:
    """Deriva lineal por la media de diferencias; banda por la desviación residual."""
    values = series.to_numpy()
    last = float(values[-1])
    drift = float(np.mean(np.diff(values))) if len(values) >= 2 else 0.0

    spread = float(np.std(values, ddof=0)) if len(values) >= 2 else 0.0
    spread = max(spread, 0.15 * abs(last))  # piso del 15% para no dar bandas planas
    z = _z(confidence)

    points: list[ForecastPoint] = []
    for i, label in enumerate(labels, start=1):
        yhat = last + drift * i
        margin = z * spread * np.sqrt(i)  # la incertidumbre crece con el horizonte
        points.append(
            ForecastPoint(
                ds=label,
                yhat=round(yhat, 2),
                yhat_lower=round(_clamp_lower(yhat - margin), 2),
                yhat_upper=round(float(yhat + margin), 2),
            )
        )
    return points


def forecast_payload(history: list[dict], periods: int, confidence: float) -> dict:
    """Forma serializable que consume Laravel."""
    points, model = forecast_series(history, periods, confidence)
    return {
        "model": model,
        "history_points": len(history),
        "confidence": confidence,
        "forecast": [asdict(p) for p in points],
    }

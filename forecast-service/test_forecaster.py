"""Tests del núcleo de forecasting (pytest)."""
from forecaster import forecast_payload, forecast_series


def _monthly(values, start_year=2024, start_month=1):
    out = []
    y, m = start_year, start_month
    for v in values:
        out.append({"ds": f"{y}-{m:02d}", "y": float(v)})
        m += 1
        if m > 12:
            m, y = 1, y + 1
    return out


def test_short_series_uses_naive():
    points, model = forecast_series(_monthly([1000, 1200]), periods=3, confidence=0.8)
    assert model == "naive"
    assert len(points) == 3


def test_forecast_count_matches_periods():
    payload = forecast_payload(_monthly(range(1000, 2200, 100)), periods=4, confidence=0.8)
    assert payload["history_points"] == 12
    assert len(payload["forecast"]) == 4


def test_band_brackets_mean_and_is_non_negative():
    points, _ = forecast_series(_monthly([100, 80, 120, 60, 90, 40]), periods=3, confidence=0.9)
    for p in points:
        assert p.yhat_lower <= p.yhat <= p.yhat_upper
        assert p.yhat_lower >= 0.0


def test_ets_trend_extends_upward_trend():
    # Serie claramente creciente → la proyección debe seguir subiendo.
    history = _monthly([1000 + 120 * i for i in range(12)])
    points, model = forecast_series(history, periods=3, confidence=0.8)
    assert model.startswith("ets")
    assert points[0].yhat > history[-1]["y"]
    assert points[2].yhat > points[0].yhat


def test_seasonal_model_kicks_in_with_two_years():
    history = _monthly([2000 + 30 * i for i in range(26)])
    _, model = forecast_series(history, periods=3, confidence=0.8)
    assert model == "ets-seasonal"

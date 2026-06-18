"""
Rikuy — Forecast Service

Microservicio de la capa de datos. Expone el forecasting de series mensuales
(statsmodels ETS) que Laravel consume para proyectar el KPI principal con su
intervalo de confianza. `/health` se mantiene como liveness probe.
"""
from __future__ import annotations

from fastapi import FastAPI
from pydantic import BaseModel, Field

from forecaster import forecast_payload

app = FastAPI(
    title="Rikuy Forecast Service",
    description="Capa de datos (forecasting / anomalías) para Rikuy.",
    version="1.0.0",
)


class HistoryPoint(BaseModel):
    ds: str = Field(..., description="Periodo mensual, formato YYYY-MM")
    y: float = Field(..., description="Valor observado (p. ej. monto facturado)")


class ForecastRequest(BaseModel):
    series: list[HistoryPoint] = Field(..., min_length=2)
    periods: int = Field(3, ge=1, le=24)
    confidence: float = Field(0.80, ge=0.50, le=0.99)


@app.get("/health")
def health() -> dict:
    """Liveness probe consumida por docker-compose y por Laravel."""
    return {"status": "ok"}


@app.get("/")
def root() -> dict:
    return {"service": "rikuy-forecast", "status": "ok", "phase": "6-forecasting"}


@app.post("/forecast")
def forecast(req: ForecastRequest) -> dict:
    """
    Proyecta `periods` meses a partir de la serie mensual recibida y devuelve la
    media (`yhat`) con su banda (`yhat_lower`/`yhat_upper`) al nivel `confidence`.
    """
    history = [point.model_dump() for point in req.series]
    return forecast_payload(history, req.periods, req.confidence)

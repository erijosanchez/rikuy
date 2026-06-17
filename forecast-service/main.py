"""
Rikuy — Forecast Service (stub)

Microservicio de la capa de datos. En la Fase 6 alojará el forecasting
(Prophet/statsmodels) y las anomalías pesadas. Por ahora solo expone /health
para validar que la pieza vive dentro del docker-compose.
"""
from fastapi import FastAPI

app = FastAPI(
    title="Rikuy Forecast Service",
    description="Capa de datos (forecasting / anomalías) para Rikuy.",
    version="0.1.0",
)


@app.get("/health")
def health() -> dict:
    """Liveness probe consumida por docker-compose y por Laravel."""
    return {"status": "ok"}


@app.get("/")
def root() -> dict:
    return {"service": "rikuy-forecast", "status": "ok", "phase": "0-cimientos"}

# Rikuy — Business Intelligence as a Product

> **Rikuy** (quechua: *ver, observar*). Plataforma de analítica comercial para
> PYMEs. Sube tu CSV y mira todo tu negocio de un vistazo: KPIs, tendencias,
> alertas, proyecciones, un asistente que responde en español y un reporte
> ejecutivo en PDF.

`Laravel 12` · `Inertia + Vue 3` · `PostgreSQL 16` · `Redis + Horizon` ·
`FastAPI + statsmodels` · `ECharts` · `Groq (function calling)` · `Browsershot` ·
`Docker`

---

## Qué es

Un mini-SaaS de inteligencia comercial pensado como pieza de portafolio con
**tres lecturas** en una sola pieza:

- **BI / Data** — modelado dimensional real (esquema estrella), capa de métricas
  con *window functions* y números **validados contra la fuente**.
- **Full-stack** — auth, multi-tenant, colas, un microservicio Python para
  forecasting e integración de IA, todo orquestado con Docker.
- **Producto** — sube tu data y entiende tu negocio en 30 segundos; alertas,
  proyecciones, asistente NL y PDF ejecutivo.

### Demo público (sandbox)

Un tenant **demo** de solo lectura, precargado con **datos abiertos reales del
Estado peruano** (órdenes de compra de PERÚ COMPRAS — Catálogos Electrónicos).
Cualquiera entra a `/demo` sin registrarse y ve la plataforma funcionando con
data creíble. Al registrarse, obtienes tu propio tenant vacío para subir tu CSV.

---

## Stack

| Capa            | Tecnología                         | Por qué                                          |
|-----------------|------------------------------------|--------------------------------------------------|
| Web + API       | Laravel 12                         | Productividad y patrón conocido                  |
| Frontend        | Vue 3 + Inertia.js                 | SPA sin API separada, vive en el repo Laravel    |
| Base de datos   | **PostgreSQL 16**                  | Window functions, CTEs y vistas materializadas   |
| Colas / cache   | Redis + Horizon                    | Ingesta y jobs pesados fuera del request         |
| Capa de datos   | **Python + FastAPI**               | Microservicio aparte para series de tiempo       |
| Forecasting     | statsmodels (ETS)                  | Proyección con intervalo de confianza            |
| Visualización   | ECharts                            | Dashboards densos tipo Grafana                   |
| Asistente NL    | Groq (RAG + function calling)      | Responde con números reales, no inventa          |
| Reportes        | Browsershot (Chromium headless)    | PDF ejecutivo imprimible                          |
| Infra           | Docker Compose + Nginx + Certbot   | Despliegue reproducible en VPS                   |

---

## Arranque rápido

Requisitos: **Docker + Docker Compose v2**.

```bash
cp app/.env.example app/.env       # variables del backend
docker compose up -d --build       # construye y levanta todo
```

Al arrancar, el contenedor `app` espera a Postgres, corre migraciones y **seedea
el tenant demo** (idempotente). Luego abre **http://localhost:8000**.

| Servicio   | URL local                     | Rol                                   |
|------------|-------------------------------|---------------------------------------|
| `app`      | http://localhost:8000         | Laravel + Inertia/Vue                 |
| `horizon`  | *(interno)*                   | Worker de colas (ingesta, jobs)       |
| `forecast` | http://localhost:8001/health  | Microservicio FastAPI                 |
| `postgres` | *(interno)*                   | Base de datos analítica               |
| `redis`    | *(interno)*                   | Cache / colas / sesiones              |

> **Seguridad:** Postgres y Redis usan `expose` (red interna de compose), no
> publican puertos al host.

El asistente NL requiere una clave de Groq (opcional para el resto):

```env
GROQ_API_KEY=gsk_...
GROQ_MODEL=llama-3.3-70b-versatile
```

---

## Características

- **Multi-tenant** — cada cuenta ve solo su data; el demo es un sandbox público
  de solo lectura.
- **Ingesta flexible** — subes CSV/Excel, mapeas tus columnas a un esquema
  canónico y un job en cola las normaliza.
- **Modelo dimensional** — esquema estrella en Postgres con vista materializada.
- **Capa de métricas** — KPIs, tendencias y breakdowns validados contra la fuente.
- **Dashboard ejecutivo** — KPIs con comparativo interanual, tendencia, top
  productos/proveedores y participación por región/entidad, con filtro de periodo.
- **Alertas** — reglas tipo "ventas caen X% vs. el mes anterior" con notificación.
- **Forecasting** — proyección del KPI principal con banda de confianza.
- **Asistente NL** — pregunta en español, responde con números reales.
- **Reporte PDF** — one-pager ejecutivo descargable.

---

## Cómo funciona

### Multi-tenancy

- **`Organization`** = tenant. Cada `User` pertenece a una; el demo es una
  organización con `is_demo = true`.
- **`TenantManager`** (singleton) sostiene el tenant activo de la request, poblado
  por el middleware **`IdentifyTenant`** (`tenant:user` o `tenant:demo`, este
  último forzado a solo-lectura).
- **`BelongsToTenant`** (trait) añade un *global scope* que filtra toda consulta
  por `organization_id` y autocompleta ese campo al crear.

### Ingesta

Tres pasos: **subir** (`POST /datasets`, valida CSV/TXT/XLSX ≤10 MB) → **mapear**
columnas a los campos canónicos (`fecha`, `producto`, `monto`, `cantidad`,
`proveedor`, `entidad`, `region`) → **procesar** (job `ProcessDataset` en Redis +
Horizon, lee por streaming con openspout y aterriza filas en `dataset_rows`).
Estados: `mapping → processing → ready | failed`.

```bash
php artisan rikuy:seed-demo                     # CSV de muestra (offline / CI)
php artisan rikuy:seed-demo --url="https://…"   # dataset real de PERÚ COMPRAS
```

### Modelo analítico

`StarSchemaBuilder` transforma las filas canónicas en un **esquema estrella**:

```
                 dim_date (conformada, global)
                      │
dim_product ──┐       │       ┌── dim_entity
dim_supplier ─┼──> fact_orders ┼── dim_region
              └──────┬─────────┘
            (monto, cantidad · aislado por tenant · trazable a su dataset)
```

- **`fact_orders`** — grano = una línea de orden, surrogate keys a cada dimensión
  y medidas aditivas (`monto`, `cantidad`).
- **`mv_orders_monthly`** — agregación mensual por tenant (vista materializada en
  Postgres refrescada tras cada build; vista normal en sqlite para tests).
- **`OrderMetrics`** — capa de métricas: `summary`, `monthlyTrend` (con
  `SUM() OVER` acumulado y `LAG()` para variación intermensual), `topProducts`,
  `bySupplier`, `byRegion`, `byEntity`, `comparison` y filtros por año/mes.

> **Validado contra la fuente:** la suma de `fact_orders` cuadra exacto con la
> suma de `dataset_rows` (en el demo: S/ 6 131 123.06 sobre 180 órdenes).

### Dashboard

Sirve las mismas métricas en **ECharts** (tree-shaking, tema oscuro): KPIs con
delta interanual, tendencia mensual (barras + acumulado + banda de forecast) y
cuatro breakdowns (productos, proveedores, región, entidad). Una barra de chips
filtra **todas** las medidas por año recargando solo las props con Inertia
(`only`, `preserveScroll`); la participación se recalcula sobre el periodo. El
tema de los charts (`charts/theme.js`) lee los design tokens en runtime.

### Alertas

- **`AlertRule`** — medida (`monto`/`ordenes`), dirección (`drop`/`rise`) y umbral
  %, aislada por tenant.
- **`AlertEvaluator`** recorre la serie mensual y registra un **`AlertEvent`** por
  cada periodo que rompe el umbral. Es **único por (regla, periodo)**: la
  evaluación es idempotente.
- **`AlertTriggered`** (canal *database*) notifica a los usuarios del tenant.
- **`rikuy:check-alerts`** (agendado a diario) evalúa todos los tenants; crear una
  regla la evalúa en el acto contra el historial.

### Forecasting

El microservicio Python (`forecast-service/`) expone **`POST /forecast`**: recibe
`{series, periods, confidence}` y devuelve `yhat` con banda
(`yhat_lower`/`yhat_upper`). Estrategia adaptativa (`forecaster.py`): ETS
estacional (≥24 meses) → ETS con tendencia amortiguada (3–23) → fallback naive
(<3). Laravel lo consume con `ForecastClient` de forma **resiliente** (si el
servicio cae, el dashboard sigue sin la banda) y `TrendChart` la dibuja sobre la
tendencia.

### Asistente de datos (NL)

Chat en español con **RAG + function calling** (Groq). El modelo solo orquesta y
redacta; las cifras salen siempre de la capa de métricas, así que **no fabrica
respuestas**. `MetricTools` expone las funciones (`periodo_reciente`,
`top_productos`, `resumen_ventas`, …) resueltas contra `OrderMetrics`;
`DataAssistant` corre el loop de tool-calling (máx. 5 rondas). Sin
`GROQ_API_KEY` se deshabilita con un aviso; si la API falla, responde con un
mensaje claro.

### Reporte PDF

`ExecutiveReport` arma el contenido desde la capa de métricas y la vista Blade
`reports/executive.blade.php` lo pinta como un one-pager A4 (tendencia en SVG
server-side). El motor está detrás de la interfaz `PdfRenderer`:
`BrowsershotPdfRenderer` (Chromium headless) en producción, `FakePdfRenderer` en
tests. La imagen `app` trae Node + Chromium + Puppeteer.

---

## Rutas

| Ruta                          | Acceso        | Qué hace                                |
|-------------------------------|---------------|-----------------------------------------|
| `/`                           | público       | Landing                                 |
| `/demo`                       | **público**   | Sandbox del tenant demo (solo lectura)  |
| `/register` · `/login`        | invitado      | Crear workspace · iniciar sesión        |
| `/dashboard`                  | autenticado   | Dashboard del tenant                    |
| `/metrics`                    | autenticado   | KPIs del tenant (JSON)                   |
| `/alerts`                     | autenticado   | Reglas de alerta y disparos             |
| `/assistant`                  | autenticado   | Asistente NL (chat + `POST` consulta)   |
| `/report/executive.pdf`       | autenticado   | Reporte ejecutivo en PDF                |
| `/demo/{metrics,alerts,assistant,report/executive.pdf}` | **público** | Equivalentes del demo |

---

## Tests

```bash
cd app && php artisan test          # 60 tests (sqlite en memoria, sin Docker)
cd forecast-service && pytest       # 5 tests del forecaster (pip install pytest)
```

Cobertura sobre lo que importa:

- **`TenantIsolationTest`** — dos cuentas no ven la data una de la otra.
- **`DatasetIngestionTest`** — subida, mapeo y procesado a filas canónicas.
- **`AnalyticsMetricsTest`** — summary/top/tendencia/breakdowns **validados contra
  la fuente** (cálculo manual) e integridad hecho↔fuente.
- **`AlertsTest`** — una regla configurada **dispara una notificación**;
  idempotencia, aislamiento por tenant.
- **`ForecastTest`** — cliente con `Http::fake`: contrato y resiliencia.
- **`AssistantTest`** — *"top 5 del último mes"* responde con **data real** vía
  function calling simulado.
- **`ExecutiveReportTest`** — descarga del PDF (cabeceras, `%PDF`, auth/demo).
- **`forecast-service/test_forecaster.py`** — modelos ETS/naive y banda.

---

## Desarrollo

**Backend / sin Docker:** PHP 8.2+, Composer, Node 20+.

**Iterar el frontend rápido** — la imagen `app` hornea los assets, así que para
no reconstruir en cada cambio crea un override de Compose (solo local) que monte
el build compilado del host:

```yaml
# docker-compose.override.yml  (gitignored)
services:
  app:
    volumes:
      - ./app/public/build:/var/www/html/public/build
```

```bash
cd app && npm run build            # se refleja al instante en :8000 (refresca)
# o: npm run dev                   # Vite con HMR (requiere exponer 5173)
```

---

## Despliegue

Guía completa en **[`DEPLOY.md`](DEPLOY.md)**: VPS con Docker Compose + Nginx +
Certbot, firewall (UFW), puertos internos y backups antes del primer dato real.

El **[`CASE_STUDY.md`](CASE_STUDY.md)** cuenta el problema, la solución por capas
y las decisiones de arquitectura (las tres lecturas: BI, full-stack, producto).

---

## Estructura del repo

```
rikuy/
├── app/                          # Laravel 12 + Inertia/Vue
│   ├── app/
│   │   ├── Alerts/               # AlertEvaluator
│   │   ├── Analytics/            # StarSchemaBuilder, OrderMetrics
│   │   ├── Assistant/            # MetricTools, GroqClient, DataAssistant
│   │   ├── Console/Commands/     # SeedDemo, CheckAlerts (rikuy:*)
│   │   ├── Forecasting/          # ForecastClient
│   │   ├── Http/{Controllers,Middleware}/
│   │   ├── Ingesta/              # CanonicalSchema, SpreadsheetReader, DatasetProcessor
│   │   ├── Jobs/                 # ProcessDataset
│   │   ├── Models/               # Organization, User, Dataset, Fact/Dim*, Alert*
│   │   ├── Notifications/        # AlertTriggered
│   │   ├── Reports/              # ExecutiveReport, PdfRenderer (Browsershot/Fake)
│   │   └── Tenancy/              # TenantManager
│   ├── resources/
│   │   ├── css/tokens.css        # design tokens (--rk-*)
│   │   ├── js/Components/         # AppShell, AuthCard, BrandMark, Charts/*
│   │   ├── js/Pages/             # Landing, Auth/*, Dashboard, Alerts, Assistant, Datasets/Map
│   │   └── views/reports/        # executive.blade.php (PDF)
│   └── tests/Feature/            # 8 suites (ver arriba)
├── forecast-service/             # FastAPI + statsmodels (main.py, forecaster.py, tests)
├── docker/app/                   # Dockerfile, nginx, supervisor, entrypoint
├── docker-compose.yml
├── DEPLOY.md
└── CASE_STUDY.md
```

---

## Design system

Todos los tokens viven en `app/resources/css/tokens.css` como CSS variables
(`--rk-*`); los componentes los **consumen** (no hay colores sueltos). Tema oscuro
tipo Grafana con superficies en capas, bordes translúcidos, foco accesible y
gradientes de marca. El chrome de la app se unifica en componentes compartidos
(`AppShell`, `AuthCard`, `BrandMark`).

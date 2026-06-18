# Rikuy — Business Intelligence as a Product

> **Rikuy** (quechua: *ver, observar*). Plataforma de analítica comercial para
> PYMEs. Sube tus datos y mira todo tu negocio de un vistazo: KPIs, tendencias,
> alertas, proyecciones y un asistente que responde preguntas sobre tu data en
> español.

La fuente de verdad del proyecto (visión, fases, reglas) está en `CLAUDE.md`.

---

## Progreso

- **Fase 0 — Cimientos ✅** — Laravel 12 + Inertia/Vue 3, Docker (Postgres,
  Redis, FastAPI stub), design tokens del tema oscuro y landing.
- **Fase 1 — Auth + Tenancy ✅** — registro/login, organizaciones (workspaces),
  aislamiento de data por tenant y sandbox demo público de solo lectura.
- **Fase 2 — Ingesta ✅** — subida de CSV/Excel con validación y mapeo de
  columnas, procesamiento en cola con Horizon y comando `rikuy:seed-demo`.
- **Fase 3 — Modelo analítico + métricas ✅** — esquema estrella en Postgres,
  vista materializada, capa de métricas con window functions y endpoints de KPIs
  validados contra la fuente.
- **Fase 4 — Dashboard ejecutivo ✅** — KPIs con comparativo interanual,
  tendencia mensual, top productos y participación por región en **ECharts**
  (tema oscuro tipo Grafana), con **filtro de periodo** por año.
- **Fase 5 — Alertas y anomalías ✅** — reglas por tenant ("ventas caen X% vs.
  mes anterior"), evaluación idempotente sobre la serie mensual, notificación a
  los usuarios y comando agendado a diario.

> Próxima: Fase 6 (Forecasting).

---

## Qué corre hoy

- **Laravel 12 + Inertia.js + Vue 3** servido por nginx + php-fpm.
- **PostgreSQL 16** y **Redis 7** (cache / colas / sesiones).
- **forecast-service**: microservicio **FastAPI** (stub) con `/health`.
- **Auth + multi-tenant**: cada cuenta tiene su propia organización (tenant) y
  solo ve su data. El tenant **demo** es un sandbox de solo lectura visible sin
  registrarse.
- **Ingesta**: subes un CSV/Excel, mapeas sus columnas a campos canónicos y un
  job en cola (**Horizon**) lo procesa a filas normalizadas.
- **Modelo analítico**: las filas se transforman en un **esquema estrella**
  (hechos/dimensiones) y los KPIs se sirven desde una capa de métricas con
  window functions y una vista materializada.
- **Dashboard ejecutivo**: KPIs, tendencia mensual, top productos y región en
  **ECharts** con filtro de periodo por año y comparativo interanual.
- **Alertas**: reglas por tenant que vigilan caídas/subidas de ventas u órdenes
  mes a mes; al romperse el umbral se registra el disparo y se notifica a los
  usuarios. Evaluación diaria vía `rikuy:check-alerts` (scheduler).
- **Design tokens** del tema oscuro tipo Grafana en `app/resources/css/tokens.css`.

---

## Requisitos

- Docker + Docker Compose v2

(Para desarrollo fuera de Docker: PHP 8.2+, Composer, Node 20+.)

---

## Levantar todo

```bash
# 1. Variables de entorno del backend
cp app/.env.example app/.env

# 2. Construir y levantar el stack
docker compose up -d --build
```

Servicios y puertos:

| Servicio   | URL local                    | Descripción                                |
|------------|------------------------------|--------------------------------------------|
| `app`      | http://localhost:8000        | Laravel + Inertia/Vue                      |
| `horizon`  | *(interno)*                  | Worker de colas (procesa la ingesta)       |
| `forecast` | http://localhost:8001/health | Microservicio FastAPI (`{"status":"ok"}`)  |
| `postgres` | *(interno)*                  | Base de datos analítica                    |
| `redis`    | *(interno)*                  | Cache / colas / sesiones                   |

> Postgres y Redis usan `expose` (no publican puertos al host) por seguridad
> (regla 7 de `CLAUDE.md`).

Al arrancar, el contenedor `app` espera a Postgres, corre migraciones y seedea
el tenant demo (idempotente).

### Rutas

| Ruta         | Acceso              | Qué hace                                      |
|--------------|---------------------|-----------------------------------------------|
| `/`          | público             | Landing                                       |
| `/demo`      | **público**         | Sandbox del tenant demo (solo lectura)        |
| `/register`  | invitado            | Crea cuenta + workspace propio                |
| `/login`     | invitado            | Inicia sesión                                 |
| `/dashboard` | autenticado         | Dashboard del tenant del usuario              |
| `/metrics`   | autenticado         | KPIs del tenant (JSON)                        |
| `/alerts`    | autenticado         | Reglas de alerta y disparos del tenant        |
| `/demo/metrics` | **público**      | KPIs del tenant demo (JSON)                   |
| `/demo/alerts`  | **público**      | Alertas del tenant demo (solo lectura)        |

---

## Multi-tenancy (Fase 1)

El aislamiento de data se apoya en tres piezas:

- **`Organization`** = tenant. Cada `User` pertenece a una (`organization_id`).
  El sandbox demo es una organización con `is_demo = true`.
- **`TenantManager`** (singleton) sostiene el tenant activo de la request. Lo
  puebla el middleware **`IdentifyTenant`**:
  - `tenant:user` → la organización del usuario autenticado.
  - `tenant:demo` → la organización demo, forzada a solo-lectura.
- **`BelongsToTenant`** (trait) añade un *global scope* que filtra toda consulta
  por `organization_id` y autocompleta ese campo al crear. Cualquier modelo con
  este trait (hoy `Dataset`) queda aislado por defecto.

### DoD de la Fase 1 ✅

- Dos cuentas distintas no ven la data una de la otra (probado en
  `tests/Feature/TenantIsolationTest.php`).
- El sandbox demo se ve sin registrarse (`GET /demo`).

```bash
# Tests (sqlite en memoria, sin Docker)
cd app && php artisan test
```

---

## Ingesta (Fase 2)

Flujo de subida en tres pasos:

1. **Subir** (`POST /datasets`) — valida el archivo (CSV/TXT/XLSX, ≤10 MB), lo
   guarda y crea el `Dataset` en estado `mapping`.
2. **Mapear** (`/datasets/{id}/map`) — el usuario asocia las columnas detectadas
   a los campos canónicos BI (`fecha`, `producto`, `monto`, `cantidad`,
   `proveedor`, `entidad`, `region`); ver `App\Ingesta\CanonicalSchema`.
3. **Procesar** — se despacha `App\Jobs\ProcessDataset` a la cola (Redis +
   Horizon). El job lee el archivo por streaming (openspout), normaliza cada fila
   y la aterriza en `dataset_rows` (JSON, aislado por tenant). El dataset pasa a
   `ready` con su conteo de filas (o `failed` con el error).

Estados del dataset: `mapping → processing → ready | failed`.

### Cargar el tenant demo

```bash
# Usa el CSV de muestra bundleado (offline / CI):
php artisan rikuy:seed-demo

# O descarga el dataset real de PERÚ COMPRAS:
php artisan rikuy:seed-demo --url="https://www.datosabiertos.gob.pe/.../ordenes.csv"
```

Es idempotente y deja el tenant demo con el hecho transaccional de PERÚ COMPRAS
(órdenes de compra de Catálogos Electrónicos). En Docker se ejecuta solo al
arrancar el contenedor `app`.

### DoD de la Fase 2 ✅

- Subir un CSV lo deja como dataset **procesado** (filas normalizadas).
- El seeder llena el tenant demo.
- Verificado: `tests/Feature/DatasetIngestionTest.php` (16 tests en total) y el
  job procesado en vivo por Horizon vía Redis.

---

## Modelo analítico (Fase 3)

Tras la ingesta, `App\Analytics\StarSchemaBuilder` transforma las filas
canónicas (`dataset_rows`) en un **esquema estrella** en Postgres:

```
                 dim_date (conformada, global)
                      │
dim_product ──┐       │       ┌── dim_entity
dim_supplier ─┼──> fact_orders ┼── dim_region
              └──────┬─────────┘
            (monto, cantidad · aislado por tenant · trazable a su dataset)
```

- **Hechos** (`fact_orders`): grano = una línea de orden, con surrogate keys a
  cada dimensión y medidas aditivas (`monto`, `cantidad`).
- **Dimensiones**: `dim_product / dim_supplier / dim_entity / dim_region`
  (por-tenant) y `dim_date` (conformada, global).
- **Vista materializada** `mv_orders_monthly`: agregación mensual por tenant
  (VISTA MATERIALIZADA en Postgres, refrescada tras cada build; vista normal en
  sqlite para tests).
- **Capa de métricas** (`App\Analytics\OrderMetrics`): `summary`, `monthlyTrend`
  (con `SUM() OVER` acumulado y `LAG()` para variación intermensual),
  `topProducts` y `byRegion`. Servida en `/metrics` y `/demo/metrics`.

El build es idempotente y se dispara desde `ProcessDataset` (cola) y desde
`rikuy:seed-demo`.

### DoD de la Fase 3 ✅

- Los endpoints de KPIs devuelven números **validados contra la fuente**: la
  suma de `fact_orders` cuadra exacto con la suma de `dataset_rows`
  (verificado en vivo: S/ 6 131 123.06 sobre 180 órdenes del demo).
- Cubierto por `tests/Feature/AnalyticsMetricsTest.php` (cálculo manual de
  summary, top productos, tendencia mensual e integridad hecho↔fuente).

---

## Dashboard ejecutivo (Fase 4)

El dashboard (`/dashboard` y `/demo`) lee las mismas props que `/metrics` y las
pinta con **ECharts** (cargado con tree-shaking, renderer canvas) sobre el tema
oscuro:

- **KPIs de cabecera** — total facturado (con **delta interanual** ▲/▼), órdenes,
  ticket promedio y unidades.
- **Tendencia mensual** — barras de monto + línea de acumulado (la window
  function `SUM() OVER`) en eje secundario.
- **Top productos** — barras horizontales con % de participación.
- **Por región** — donut de participación (la cola larga se agrupa en *Otras*).

### Filtro de periodo

Una barra de chips (`Todo` + cada año con data) recorta **todas** las medidas al
año elegido. El front recarga solo las props de métricas con Inertia
(`only: [...]`, `preserveScroll`) y el back valida el año contra
`OrderMetrics::availableYears()` (un año inválido cae a *Todo*). La participación
se recalcula sobre el total del periodo, no sobre el global.

- **Tema de charts**: `app/resources/js/charts/theme.js` lee los design tokens
  (`--rk-*`) en runtime, así los gráficos respetan el design system (regla 4).
- **Componentes**: `Components/Charts/BaseChart.vue` (init/resize/dispose) +
  `TrendChart`, `TopProductsChart`, `RegionChart`.

### DoD de la Fase 4 ✅

- El dashboard del tenant demo se ve y los **filtros de periodo funcionan**
  (recortan KPIs, tendencia y breakdowns).
- El comparativo interanual y el filtro están cubiertos en
  `tests/Feature/AnalyticsMetricsTest.php` (años disponibles, recorte por año,
  comparativo vs año previo y endpoint `/metrics?year=`).

---

## Alertas y anomalías (Fase 5)

Reglas simples por tenant que vigilan la variación **intermensual** de una medida:

- **`AlertRule`** (`alert_rules`): medida (`monto` = ventas | `ordenes`),
  dirección (`drop` = cae | `rise` = sube) y umbral en %. Aislada por tenant.
- **`AlertEvaluator`** recorre la serie mensual (`OrderMetrics::monthlyTrend`) y,
  por cada par de meses cuya variación rompe el umbral en la dirección dada,
  registra un **`AlertEvent`**. El evento es **único por (regla, periodo)**: la
  evaluación es idempotente y no duplica disparos al reejecutarse.
- **`AlertTriggered`** (notificación, canal *database*) llega a los usuarios del
  tenant; los disparos también quedan en el log visible en `/alerts`.
- **`rikuy:check-alerts`** evalúa todos los tenants (o uno con `--tenant=slug`) y
  notifica los disparos nuevos. Agendado a diario en `routes/console.php`.

Crear una regla en `/alerts` la evalúa **en el acto** contra el historial: si ya
hay periodos que la rompen, se disparan y notifican al instante.

> Aislamiento: el route-model binding corre antes de que el middleware fije el
> tenant, así que `AlertController` verifica la propiedad de la regla
> explícitamente (404 si es de otro tenant), además del global scope.

### DoD de la Fase 5 ✅

- Una regla configurada **dispara una notificación** (probado en
  `tests/Feature/AlertsTest.php`: comando + endpoint con `Notification::fake`).
- Cubre además idempotencia, reglas pausadas, dirección `rise`/`drop`,
  aislamiento por tenant y la página demo pública de solo lectura.

```bash
# Evaluar alertas manualmente (todos los tenants o uno):
php artisan rikuy:check-alerts
php artisan rikuy:check-alerts --tenant=demo
```

---

## Design system (tema oscuro tipo Grafana)

Todos los tokens viven en `app/resources/css/tokens.css` como CSS variables con
prefijo `--rk-`. Los componentes **consumen** estas variables; no se definen
colores sueltos.

> Regla 4 de `CLAUDE.md`: el design system no se toca fuera de la Fase 0 sin
> actualizar la documentación.

---

## Estructura del repo

```
rikuy/
├── app/                          # Laravel 12 + Inertia/Vue
│   ├── app/
│   │   ├── Alerts/               # AlertEvaluator (reglas vs. serie mensual)
│   │   ├── Analytics/            # StarSchemaBuilder, OrderMetrics
│   │   ├── Console/Commands/     # SeedDemo, CheckAlerts (rikuy:*)
│   │   ├── Http/Controllers/     # Auth, Dashboard, Dataset, Metrics, Alert
│   │   ├── Http/Middleware/      # IdentifyTenant, HandleInertiaRequests
│   │   ├── Ingesta/              # CanonicalSchema, SpreadsheetReader, DatasetProcessor
│   │   ├── Jobs/                 # ProcessDataset
│   │   ├── Models/               # Organization, User, Dataset, DatasetRow, Fact/Dim*, Alert* (+ Concerns)
│   │   ├── Notifications/        # AlertTriggered
│   │   └── Tenancy/              # TenantManager
│   ├── database/seeders/data/    # CSV de muestra de PERÚ COMPRAS
│   ├── resources/
│   │   ├── css/tokens.css        # design tokens
│   │   └── js/
│   │       ├── charts/theme.js   # tema de ECharts desde los design tokens
│   │       ├── Components/Charts/ # BaseChart, Trend/TopProducts/Region
│   │       └── Pages/            # Landing, Auth/*, Dashboard, Alerts, Datasets/Map
│   └── tests/Feature/            # Auth, TenantIsolation, DatasetIngestion, AnalyticsMetrics, Alerts
├── forecast-service/             # microservicio FastAPI (stub)
├── docker/app/                   # Dockerfile, nginx, supervisor, entrypoint
├── docker-compose.yml
└── CLAUDE.md
```

---

## Desarrollo de frontend (opcional, con hot reload)

```bash
cd app
npm install
npm run dev      # Vite con HMR
```

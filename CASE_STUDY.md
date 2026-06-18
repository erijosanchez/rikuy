# Caso de estudio — Rikuy

> **Business Intelligence as a Product.** Una sola pieza con tres lecturas: el
> modelado y las métricas para un perfil de **BI/Data**, la arquitectura para uno
> de **full-stack**, y la experiencia para uno de **producto**.

---

## El problema

Las PYMEs tienen datos (un export de su ERP, un CSV de ventas) pero no tienen
analítica. Las herramientas de BI tradicionales piden modelar, conectar y
configurar antes de mostrar un solo número. Rikuy invierte el flujo: **subes tu
CSV y ves tu negocio de un vistazo** — KPIs, tendencias, alertas, proyecciones y
un asistente que responde en español.

Para que cualquiera lo vea funcionando sin registrarse, hay un **sandbox demo**
precargado con **datos abiertos reales del Estado peruano** (órdenes de compra de
PERÚ COMPRAS, Catálogos Electrónicos).

---

## La solución, por capas

### 1. Ingesta flexible (no asume tu esquema)
Subes CSV/Excel, **mapeas tus columnas** a un esquema canónico (`fecha`,
`producto`, `monto`, `cantidad`, `proveedor`, `entidad`, `region`) y un job en
cola (Redis + Horizon) normaliza cada fila por streaming. El dataset queda
trazable y aislado por tenant.

### 2. Modelado dimensional de verdad
Las filas canónicas se transforman en un **esquema estrella** en PostgreSQL:
`fact_orders` (grano = línea de orden, medidas aditivas) apuntando a
`dim_product / dim_supplier / dim_entity / dim_region` (por-tenant) y `dim_date`
(conformada, global). Una **vista materializada** (`mv_orders_monthly`) agrega por
mes y la capa de métricas usa **window functions** (`SUM() OVER`, `LAG()`) para
acumulado y variación intermensual.

> **Cifras validadas contra la fuente:** la suma de `fact_orders` cuadra exacto
> con la suma de `dataset_rows` — los números no son de adorno.

### 3. Dashboard ejecutivo
KPIs con comparativo interanual, tendencia mensual, top productos y participación
por región en **ECharts** (tema oscuro tipo Grafana), con **filtro de periodo**.

### 4. Alertas y anomalías
Reglas por tenant ("ventas caen X% vs. el mes anterior"); la evaluación es
**idempotente** (única por regla+periodo) y notifica a los usuarios. Corre a
diario en el scheduler.

### 5. Forecasting
Un **microservicio Python (FastAPI + statsmodels ETS)**, separado del monolito,
proyecta el KPI principal con **intervalo de confianza**. Laravel lo consume de
forma resiliente y pinta la banda sobre la tendencia.

### 6. Asistente de datos (NL)
Preguntas en español respondidas con **números reales** vía **function calling**
(Groq) sobre la capa de métricas. El modelo solo orquesta y redacta; las cifras
salen siempre de herramientas deterministas, así que **no fabrica respuestas**.

### 7. Reporte ejecutivo en PDF
Un one-pager imprimible (Browsershot/Chromium headless) con los mismos números
del dashboard, descargable desde la app.

---

## Decisiones de arquitectura

- **Multi-tenant desde el día 1** con un *global scope* (`BelongsToTenant`) que
  filtra toda consulta por `organization_id` y un sandbox demo de solo lectura.
- **PostgreSQL** en vez de MySQL por las window functions y CTEs (analítica).
- **Microservicio Python** solo para lo que aporta (series de tiempo), sin
  reescribir el resto del stack ya dominado (Laravel/Inertia/Vue).
- **Resiliencia**: si el forecast-service o Groq no responden, la app degrada con
  gracia en vez de romper.
- **Seguridad** (lección aprendida de un incidente de ransomware): en el VPS,
  Postgres/Redis no exponen puertos (`expose`, no `ports`), UFW cerrado y backups
  automatizados antes del primer deploy con data real.

---

## Stack

Laravel 12 · Inertia.js · Vue 3 · PostgreSQL 16 · Redis 7 + Horizon · FastAPI +
statsmodels · ECharts · Groq (function calling) · Browsershot · Docker Compose +
Nginx + Certbot.

---

## Resultados

- MVP completo y desplegable: de CSV crudo a dashboard, alertas, forecast,
  asistente NL y PDF ejecutivo.
- Cobertura de tests sobre lo que importa: aislamiento por tenant, ingesta,
  métricas **validadas contra la fuente**, alertas, forecasting, asistente y
  reporte PDF.
- Demo público con data real para que la plataforma se entienda en 30 segundos,
  sin registro.

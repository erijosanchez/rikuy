# Deploy — Rikuy en un VPS

Guía para poner Rikuy **público en vivo** con Docker Compose + Nginx + Certbot.
Pensada para un VPS Debian/Ubuntu. Aplica la **regla 7** de `CLAUDE.md`:
seguridad desde el día 1.

---

## 0. Requisitos en el servidor

- Docker + Docker Compose v2.
- Un dominio apuntando al VPS (registro `A`).
- Usuario no-root con acceso a Docker.

---

## 1. Firewall (antes que nada)

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow OpenSSH
sudo ufw allow 80
sudo ufw allow 443
sudo ufw enable
```

> Postgres y Redis **no** se exponen: en `docker-compose.yml` usan `expose`
> (red interna de compose), nunca `ports`. No abras 5432/6379 en UFW.

---

## 2. Clonar y configurar

```bash
git clone <repo> rikuy && cd rikuy
cp app/.env.example app/.env
```

Edita `app/.env` (mínimos para producción):

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com
APP_KEY=                      # se genera abajo

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_DATABASE=rikuy
DB_USERNAME=rikuy
DB_PASSWORD=<secreto-fuerte>  # igual que en docker-compose

REDIS_HOST=redis
QUEUE_CONNECTION=redis

# Forecasting (microservicio interno)
FORECAST_SERVICE_URL=http://forecast:8000

# Asistente NL (opcional pero recomendado para el demo)
GROQ_API_KEY=gsk_...
GROQ_MODEL=llama-3.3-70b-versatile

# Reportes PDF (Chromium ya viene en la imagen app)
BROWSERSHOT_ENABLED=true
BROWSERSHOT_CHROME_PATH=/usr/bin/chromium
BROWSERSHOT_NO_SANDBOX=true
```

> **Secrets solo en `.env`** (regla 6). Nunca commitear `.env`.

---

## 3. Levantar el stack

```bash
docker compose up -d --build
```

El contenedor `app` espera a Postgres, corre migraciones y seedea el tenant demo
(idempotente) en el `entrypoint`. Genera la app key si falta:

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan rikuy:seed-demo    # data real (opcional: --url=...)
```

Servicios: `app` (web), `horizon` (colas), `forecast` (FastAPI), `postgres`,
`redis`. Solo `app` (y `forecast` para debug) publican puertos.

---

## 4. Nginx + TLS (Certbot)

El reverse proxy del host termina TLS y enruta al contenedor `app`. Ejemplo con
Certbot (nginx del host):

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d tu-dominio.com
```

Proxy del host → `app` (puerto publicado por compose, p. ej. 8000):

```nginx
location / {
    proxy_pass http://127.0.0.1:8000;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
}
```

Con `APP_URL=https://...`, Laravel fuerza la URL base correcta tras el proxy
(`AppServiceProvider`).

---

## 5. Backups (antes del primer dato real)

Dump diario de Postgres a un volumen/destino externo:

```bash
docker compose exec -T postgres pg_dump -U rikuy rikuy | gzip > rikuy-$(date +%F).sql.gz
```

Agéndalo en cron y rota copias. **Backups automatizados antes del primer deploy
con data real** (regla 7).

---

## 6. Verificación (DoD Fase 8)

- [ ] `https://tu-dominio.com/` carga la landing.
- [ ] `https://tu-dominio.com/demo` muestra el dashboard con data real.
- [ ] El **PDF ejecutivo** descarga desde el botón del dashboard
      (`/demo/report/executive.pdf`).
- [ ] El asistente responde (si `GROQ_API_KEY` está configurada).

---

## Operación

```bash
docker compose logs -f app          # logs
docker compose exec app php artisan horizon:status
docker compose pull && docker compose up -d --build   # actualizar
```

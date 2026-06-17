#!/bin/sh
set -e

cd /var/www/html

# Garantiza un .env (las variables reales llegan vía env_file y tienen prioridad)
if [ ! -f .env ]; then
    cp .env.example .env
fi

# APP_KEY: si no viene por entorno ni en .env, generarla
if [ -z "$APP_KEY" ] && ! grep -q "^APP_KEY=base64" .env; then
    php artisan key:generate --force
fi

# Descubrir paquetes (se hace aquí porque el build usa --no-scripts)
php artisan package:discover --ansi || true

# Esperar a Postgres antes de migrar
DB_HOST="${DB_HOST:-postgres}"
DB_PORT="${DB_PORT:-5432}"
echo "Esperando a Postgres en ${DB_HOST}:${DB_PORT}..."
until php -r "exit(@fsockopen(getenv('DB_HOST')?:'postgres',(int)(getenv('DB_PORT')?:5432))?0:1);" 2>/dev/null; do
    sleep 1
done
echo "Postgres listo."

php artisan migrate --force || true
php artisan storage:link || true

exec "$@"

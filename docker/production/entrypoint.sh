#!/bin/bash
# =============================================================================
# CotaG - Container entrypoint (app e worker)
# =============================================================================
# Responsabilidades:
#   - configurar FreeTDS para o Replicado
#   - garantir diretórios/permissões do storage
#   - obter/gerar APP_KEY persistente
#   - aguardar o banco de dados
#   - rodar migrations (somente no container app)
#   - otimizar caches (somente no container app)
#   - iniciar Nginx + PHP-FPM (app) ou queue worker (worker)
# =============================================================================

set -e

ROLE="${CONTAINER_ROLE:-app}"
echo "🚀 Starting CotaG container (role: ${ROLE})..."

# -----------------------------------------------------------------------------
# FreeTDS (Replicado)
# -----------------------------------------------------------------------------
echo "🔧 Configuring FreeTDS for Replicado..."
REPLICADO_HOST_VAL="${REPLICADO_HOST:-localhost}"
REPLICADO_PORT_VAL="${REPLICADO_PORT:-1433}"

sed -e "s/__REPLICADO_HOST__/${REPLICADO_HOST_VAL}/g" \
    -e "s/__REPLICADO_PORT__/${REPLICADO_PORT_VAL}/g" \
    /etc/freetds/freetds.conf.template > /etc/freetds/freetds.conf

echo "✅ FreeTDS configured: ${REPLICADO_HOST_VAL}:${REPLICADO_PORT_VAL}"

# -----------------------------------------------------------------------------
# Storage
# -----------------------------------------------------------------------------
echo "📁 Setting up storage directories..."
mkdir -p storage/framework/{cache,sessions,views}
mkdir -p storage/framework/cache/data
mkdir -p storage/logs
mkdir -p storage/app/private
mkdir -p storage/app/public
mkdir -p bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Dependências (apenas no cenário de bind-mount de desenvolvimento)
if [ ! -d vendor ]; then
    echo "📦 Installing Composer dependencies..."
    composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader
fi

# -----------------------------------------------------------------------------
# APP_KEY
# -----------------------------------------------------------------------------
# Em produção prefira definir APP_KEY nas variáveis do stack (Portainer).
# Quando ausente, geramos e persistimos em storage/app/app_key para que app e
# worker compartilhem a mesma chave.
KEY_FILE=/var/www/html/storage/app/app_key

if [ -z "${APP_KEY:-}" ] || ! printf '%s' "$APP_KEY" | grep -q '^base64:'; then
    echo "🔑 APP_KEY ausente ou inválida, obtendo/gerando..."

    if [ "$ROLE" = "worker" ]; then
        for i in $(seq 1 60); do
            [ -f "$KEY_FILE" ] && break
            sleep 2
        done
    fi

    if [ -f "$KEY_FILE" ]; then
        APP_KEY="$(cat "$KEY_FILE")"
    else
        APP_KEY="$(php artisan key:generate --show --no-ansi)"
        printf '%s\n' "$APP_KEY" > "$KEY_FILE"
        chown www-data:www-data "$KEY_FILE"
        chmod 600 "$KEY_FILE"
    fi

    export APP_KEY
    echo "✅ APP_KEY definida (persistida em ${KEY_FILE})"
fi

# -----------------------------------------------------------------------------
# Banco de dados
# -----------------------------------------------------------------------------
echo "⏳ Waiting for database..."
attempt=0
until php artisan db:show > /dev/null 2>&1; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 60 ]; then
        echo "❌ Banco de dados inacessível após 120s. Verifique DB_* no stack." >&2
        exit 1
    fi
    sleep 2
done
echo "✅ Database is ready!"

# -----------------------------------------------------------------------------
# Inicialização da aplicação (somente no container app)
# -----------------------------------------------------------------------------
if [ "$ROLE" = "app" ]; then
    if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
        echo "🔧 Running migrations..."
        php artisan migrate --force --no-interaction
    else
        echo "⏭️  Migrations desabilitadas (RUN_MIGRATIONS=${RUN_MIGRATIONS:-true})"
    fi

    if [ "${RUN_SEEDERS:-false}" = "true" ]; then
        echo "🌱 Running seeders..."
        php artisan db:seed --force --no-interaction || echo "⚠️  Seeding failed"
    fi

    if [ "${APP_ENV:-production}" = "production" ]; then
        echo "⚡ Optimizing application..."
        # route:cache não é usado: routes/web.php possui closures.
        php artisan view:clear || true
        php artisan view:cache || true
        php artisan filament:optimize || true
    else
        echo "⚡ Skipping optimizations (APP_ENV=${APP_ENV:-production})"
    fi

    chown -R www-data:www-data storage bootstrap/cache
fi

echo "✨ Container ready! Starting services..."

if [ "$ROLE" = "worker" ]; then
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.worker.conf
fi

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

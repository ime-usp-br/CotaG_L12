#!/usr/bin/env bash
# =============================================================================
# CotaG - Container entrypoint (app e worker)
# =============================================================================
# Responsabilidades:
#   - validar CONTAINER_ROLE, APP_ENV e WEB_PORT
#   - configurar FreeTDS para o Replicado
#   - preparar storage, Nginx e permissoes
#   - obter/gerar APP_KEY persistente (somente no app)
#   - resolver o banco externo por dominio e aguardar disponibilidade
#   - rodar migrations/seeders/caches somente no app
#   - iniciar Nginx + PHP-FPM (app) ou queue worker (worker)
#   - fazer drop de privilegios para www-data via gosu
# =============================================================================

set -Eeuo pipefail

ROLE="${CONTAINER_ROLE:-app}"
APP_ENV_VALUE="${APP_ENV:-}"
WEB_PORT_VALUE="${WEB_PORT:-8080}"
APP_ROOT="/var/www/html"
SUPERVISOR_CONF="/etc/supervisor/conf.d/supervisord.conf"

log() {
    printf '%s\n' "$*"
}

fail() {
    printf 'ERRO: %s\n' "$*" >&2
    exit 1
}

case "$ROLE" in
    app|worker)
        ;;
    *)
        fail "CONTAINER_ROLE invalido: ${ROLE}. Use app ou worker."
        ;;
esac

case "$APP_ENV_VALUE" in
    dev|homolog|prod|local|production|staging)
        ;;
    '')
        fail "APP_ENV nao definido. Use dev, homolog ou prod."
        ;;
    *)
        fail "APP_ENV invalido: ${APP_ENV_VALUE}. Use dev, homolog ou prod."
        ;;
esac

if ! [[ "$WEB_PORT_VALUE" =~ ^[0-9]+$ ]] || (( WEB_PORT_VALUE < 1 || WEB_PORT_VALUE > 65535 )); then
    fail "WEB_PORT invalido: ${WEB_PORT_VALUE}."
fi

if [[ "$ROLE" == "worker" ]]; then
    SUPERVISOR_CONF="/etc/supervisor/conf.d/supervisord.worker.conf"
fi

log "Iniciando CotaG (role=${ROLE}, APP_ENV=${APP_ENV_VALUE}, WEB_PORT=${WEB_PORT_VALUE})."

# -----------------------------------------------------------------------------
# FreeTDS (Replicado)
# -----------------------------------------------------------------------------
REPLICADO_HOST_VAL="${REPLICADO_HOST:-localhost}"
REPLICADO_PORT_VAL="${REPLICADO_PORT:-1433}"

if [[ -f /etc/freetds/freetds.conf.template ]]; then
    sed -e "s/__REPLICADO_HOST__/${REPLICADO_HOST_VAL}/g" \
        -e "s/__REPLICADO_PORT__/${REPLICADO_PORT_VAL}/g" \
        /etc/freetds/freetds.conf.template > /etc/freetds/freetds.conf
    log "FreeTDS configurado para ${REPLICADO_HOST_VAL}:${REPLICADO_PORT_VAL}."
fi

# -----------------------------------------------------------------------------
# Storage, Nginx e permissoes
# -----------------------------------------------------------------------------
log "Preparando diretorios de runtime..."
mkdir -p \
    "$APP_ROOT/storage/framework/cache" \
    "$APP_ROOT/storage/framework/cache/data" \
    "$APP_ROOT/storage/framework/sessions" \
    "$APP_ROOT/storage/framework/views" \
    "$APP_ROOT/storage/logs" \
    "$APP_ROOT/storage/app/private" \
    "$APP_ROOT/storage/app/public" \
    "$APP_ROOT/storage/backups" \
    "$APP_ROOT/bootstrap/cache" \
    /tmp/nginx/client_body \
    /tmp/nginx/proxy \
    /tmp/nginx/fastcgi \
    /tmp/nginx/uwsgi \
    /tmp/nginx/scgi

if [[ -f /etc/nginx/nginx.conf.template ]]; then
    sed "s/__WEB_PORT__/${WEB_PORT_VALUE}/g" \
        /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf
fi

if [[ "$ROLE" == "app" ]]; then
    nginx -t
    php-fpm -t
fi

if [[ "$(id -u)" -eq 0 ]]; then
    chown -R www-data:www-data \
        "$APP_ROOT/storage" \
        "$APP_ROOT/bootstrap/cache" \
        /tmp/nginx
    chmod -R 775 "$APP_ROOT/storage" "$APP_ROOT/bootstrap/cache"
fi

# A imagem de runtime deve conter as dependencias; nao instalamos Composer
# em producao nem dependemos de bind mount de codigo.
if [[ ! -d "$APP_ROOT/vendor" ]]; then
    fail "vendor ausente em ${APP_ROOT}. Construa a imagem novamente; o runtime nao instala dependencias."
fi

# -----------------------------------------------------------------------------
# APP_KEY
# -----------------------------------------------------------------------------
KEY_FILE="$APP_ROOT/storage/app/app_key"

if [[ -z "${APP_KEY:-}" ]] || ! printf '%s' "${APP_KEY:-}" | grep -q '^base64:'; then
    log "APP_KEY ausente ou invalida; obtendo/gerando chave persistente."

    if [[ "$ROLE" == "worker" ]]; then
        for _ in $(seq 1 60); do
            [[ -f "$KEY_FILE" ]] && break
            sleep 2
        done

        [[ -f "$KEY_FILE" ]] || fail "APP_KEY ausente e o app ainda nao gerou ${KEY_FILE}."
        APP_KEY="$(cat "$KEY_FILE")"
    else
        if [[ -f "$KEY_FILE" ]]; then
            APP_KEY="$(cat "$KEY_FILE")"
        else
            APP_KEY="$(php artisan key:generate --show --no-ansi)"
            printf '%s\n' "$APP_KEY" > "$KEY_FILE"
            if [[ "$(id -u)" -eq 0 ]]; then
                chown www-data:www-data "$KEY_FILE"
            fi
            chmod 600 "$KEY_FILE"
        fi
    fi

    export APP_KEY
    log "APP_KEY definida a partir de ${KEY_FILE}."
fi

# -----------------------------------------------------------------------------
# Banco externo por dominio
# -----------------------------------------------------------------------------
# shellcheck source=/dev/null
source "$APP_ROOT/scripts/lib-db.sh"

db_load_env
db_validate_required
db_require_credentials

log "Banco externo: ${DB_HOST}:${DB_PORT}/${DB_DATABASE}."

"$APP_ROOT/scripts/wait-for-db.sh"

# -----------------------------------------------------------------------------
# Inicializacao da aplicacao (somente no container app)
# -----------------------------------------------------------------------------
if [[ "$ROLE" == "app" ]]; then
    if [[ "${RUN_MIGRATIONS:-true}" == "true" ]]; then
        log "Rodando migrations..."
        php artisan migrate --force --no-interaction
    else
        log "Migrations desabilitadas (RUN_MIGRATIONS=${RUN_MIGRATIONS:-true})."
    fi

    if [[ "${RUN_SEEDERS:-false}" == "true" ]]; then
        case "$APP_ENV_VALUE" in
            prod|production|staging)
                fail "RUN_SEEDERS=true nao e permitido em APP_ENV=${APP_ENV_VALUE}."
                ;;
            *)
                log "Rodando seeders..."
                php artisan db:seed --force --no-interaction
                ;;
        esac
    fi

    if [[ "${WARM_CACHE:-true}" == "true" ]]; then
        case "$APP_ENV_VALUE" in
            dev|local)
                log "Warm cache ignorado em APP_ENV=${APP_ENV_VALUE}."
                ;;
            *)
                log "Aquecendo caches..."
                php artisan config:cache
                php artisan view:cache
                php artisan filament:optimize || true
                ;;
        esac
    else
        log "Warm cache desabilitado; limpando caches de config/view."
        php artisan config:clear || true
        php artisan view:clear || true
    fi

    php artisan storage:link || true

    if [[ "$(id -u)" -eq 0 ]]; then
        chown -R www-data:www-data "$APP_ROOT/storage" "$APP_ROOT/bootstrap/cache"
        chmod -R 775 "$APP_ROOT/storage" "$APP_ROOT/bootstrap/cache"
    fi
fi

log "Container pronto; iniciando servicos."

# O PID 1 (supervisord) e os masters de nginx/php-fpm permanecem como root para
# abrir os pipes de log e portas internas. Os workers de nginx/php-fpm e o
# queue worker rodam como www-data (user=www-data nos arquivos de configuracao).
exec /usr/bin/supervisord -c "$SUPERVISOR_CONF"

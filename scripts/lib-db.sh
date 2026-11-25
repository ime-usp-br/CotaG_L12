#!/usr/bin/env bash
# =============================================================================
# CotaG - Helpers de banco externo (compartilhados pelos scripts)
# =============================================================================
# Este arquivo e carregado por wait-for-db.sh, backup-db.sh e restore-db.sh.
# Ele nunca imprime senha, token ou connection string completa.
# =============================================================================

db_urldecode() {
    local data="${1//+/ }"
    printf '%b' "${data//%/\\x}"
}

db_engine() {
    case "${DB_CONNECTION:-mysql}" in
        mysql|mariadb)
            printf '%s\n' mysql
            ;;
        pgsql|postgres|postgresql)
            printf '%s\n' postgres
            ;;
        *)
            printf 'ERRO: DB_CONNECTION=%s nao suportado. Use mysql, mariadb ou pgsql.\n' "${DB_CONNECTION:-}" >&2
            return 1
            ;;
    esac
}

db_parse_url() {
    local url="$1"
    local scheme rest hostpart hostport path userinfo
    local url_host url_port url_user url_pass url_db

    scheme="${url%%://*}"
    rest="${url#*://}"
    rest="${rest%%\?*}"

    if [[ "$rest" == */* ]]; then
        hostpart="${rest%%/*}"
        path="${rest#*/}"
    else
        hostpart="$rest"
        path=""
    fi

    if [[ "$hostpart" == *@* ]]; then
        userinfo="${hostpart%@*}"
        hostport="${hostpart##*@}"
    else
        userinfo=""
        hostport="$hostpart"
    fi

    if [[ -n "$userinfo" ]]; then
        url_user="$(db_urldecode "${userinfo%%:*}")"
        if [[ "$userinfo" == *:* ]]; then
            url_pass="$(db_urldecode "${userinfo#*:}")"
        fi
    fi

    if [[ "$hostport" =~ ^\[(.+)\]:([0-9]+)$ ]]; then
        url_host="${BASH_REMATCH[1]}"
        url_port="${BASH_REMATCH[2]}"
    elif [[ "$hostport" == *:* ]]; then
        url_host="${hostport%%:*}"
        url_port="${hostport##*:}"
    else
        url_host="$hostport"
    fi

    if [[ -n "$path" ]]; then
        url_db="$(db_urldecode "${path%%\?*}")"
    fi

    case "$scheme" in
        mysql|mariadb)
            DB_CONNECTION="${DB_CONNECTION:-mysql}"
            ;;
        pgsql|postgres|postgresql)
            DB_CONNECTION="${DB_CONNECTION:-pgsql}"
            ;;
        *)
            printf 'ERRO: esquema de DB_URL/DATABASE_URL nao suportado: %s\n' "$scheme" >&2
            return 1
            ;;
    esac

    if [[ -z "${DB_HOST:-}" && -n "${url_host:-}" ]]; then
        DB_HOST="$url_host"
    fi
    if [[ -z "${DB_PORT:-}" && -n "${url_port:-}" ]]; then
        DB_PORT="$url_port"
    fi
    if [[ -z "${DB_DATABASE:-}" && -n "${url_db:-}" ]]; then
        DB_DATABASE="$url_db"
    fi
    if [[ -z "${DB_USERNAME:-}" && -n "${url_user:-}" ]]; then
        DB_USERNAME="$url_user"
    fi
    if [[ -z "${DB_PASSWORD:-}" && -n "${url_pass:-}" ]]; then
        DB_PASSWORD="$url_pass"
    fi

    if [[ -z "${DB_URL:-}" ]]; then
        DB_URL="$url"
    fi
}

db_load_env() {
    local url="${DB_URL:-${DATABASE_URL:-}}"

    DB_HOST="${DB_HOST:-}"
    DB_PORT="${DB_PORT:-}"
    DB_DATABASE="${DB_DATABASE:-}"
    DB_USERNAME="${DB_USERNAME:-}"
    DB_PASSWORD="${DB_PASSWORD:-}"
    DB_URL="${DB_URL:-}"
    DATABASE_URL="${DATABASE_URL:-}"

    if [[ -n "$url" ]]; then
        db_parse_url "$url" || return 1
    fi

    # Aliases aceitos pelo contrato (DB_* e variantes curtas)
    if [[ -z "${DB_DATABASE:-}" && -n "${DB_NAME:-}" ]]; then
        DB_DATABASE="$DB_NAME"
    fi
    if [[ -z "${DB_USERNAME:-}" && -n "${DB_USER:-}" ]]; then
        DB_USERNAME="$DB_USER"
    fi
    if [[ -z "${DB_PASSWORD:-}" && -n "${DB_PASS:-}" ]]; then
        DB_PASSWORD="$DB_PASS"
    fi

    DB_CONNECTION="${DB_CONNECTION:-mysql}"

    local engine
    engine="$(db_engine)" || return 1

    if [[ -z "${DB_PORT:-}" ]]; then
        case "$engine" in
            mysql) DB_PORT=3306 ;;
            postgres) DB_PORT=5432 ;;
        esac
    fi

    export DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
    export DB_URL DATABASE_URL
}

db_validate_host() {
    local host="${DB_HOST:-}"
    local lower="${host,,}"

    if [[ -z "$host" ]]; then
        printf 'ERRO: DB_HOST nao definido. Use o dominio do banco do ambiente.\n' >&2
        return 1
    fi

    case "$lower" in
        db|mysql|mariadb|postgres|postgresql|localhost|127.0.0.1|::1|host.docker.internal)
            printf 'ERRO: DB_HOST=%s nao e um banco externo valido. Use o dominio DNS do banco do ambiente.\n' "$host" >&2
            return 1
            ;;
    esac
}

db_validate_required() {
    local missing=0
    local var
    for var in DB_HOST DB_PORT DB_DATABASE DB_USERNAME; do
        if [[ -z "${!var:-}" ]]; then
            printf 'ERRO: %s nao definido.\n' "$var" >&2
            missing=1
        fi
    done

    if [[ "$missing" -ne 0 ]]; then
        return 1
    fi

    if ! [[ "${DB_PORT}" =~ ^[0-9]+$ ]] || (( DB_PORT < 1 || DB_PORT > 65535 )); then
        printf 'ERRO: DB_PORT=%s invalido.\n' "${DB_PORT}" >&2
        return 1
    fi

    db_validate_host
}

db_require_credentials() {
    if [[ -z "${DB_PASSWORD:-}" ]]; then
        printf 'ERRO: DB_PASSWORD nao definido. Configure a senha no Portainer/ambiente.\n' >&2
        return 1
    fi
}

db_tcp_check() {
    timeout "${DB_TCP_TIMEOUT:-5}" bash -c "exec 3<>/dev/tcp/${DB_HOST}/${DB_PORT}" 2>/dev/null
}

db_wait() {
    local timeout="${WAIT_FOR_DB_TIMEOUT:-120}"
    local interval="${WAIT_FOR_DB_INTERVAL:-2}"
    local elapsed=0

    printf 'Aguardando banco externo em %s:%s (timeout %ss)...\n' "${DB_HOST}" "${DB_PORT}" "$timeout"

    while (( elapsed < timeout )); do
        if db_tcp_check; then
            printf 'Banco acessivel em %s:%s.\n' "${DB_HOST}" "${DB_PORT}"
            return 0
        fi
        sleep "$interval"
        elapsed=$((elapsed + interval))
    done

    printf 'ERRO: banco %s:%s nao respondeu em %ss. Verifique DNS, firewall e DB_*.\n' \
        "${DB_HOST}" "${DB_PORT}" "$timeout" >&2
    return 1
}

db_check_connection() {
    local engine
    engine="$(db_engine)" || return 1

    case "$engine" in
        mysql)
            command -v mysql >/dev/null 2>&1 || {
                printf 'ERRO: cliente mysql nao encontrado. Instale default-mysql-client ou rode no container da aplicacao.\n' >&2
                return 1
            }
            MYSQL_PWD="${DB_PASSWORD}" mysql \
                --protocol=TCP \
                --connect-timeout=10 \
                -h "${DB_HOST}" \
                -P "${DB_PORT}" \
                -u "${DB_USERNAME}" \
                -e 'SELECT 1' \
                "${DB_DATABASE}" >/dev/null
            ;;
        postgres)
            command -v psql >/dev/null 2>&1 || {
                printf 'ERRO: cliente psql nao encontrado. Instale postgresql-client ou rode no container da aplicacao.\n' >&2
                return 1
            }
            PGPASSWORD="${DB_PASSWORD}" psql \
                -h "${DB_HOST}" \
                -p "${DB_PORT}" \
                -U "${DB_USERNAME}" \
                -d "${DB_DATABASE}" \
                -v ON_ERROR_STOP=1 \
                -c 'SELECT 1' >/dev/null
            ;;
    esac
}

db_sha256_create() {
    local file="$1"
    if command -v sha256sum >/dev/null 2>&1; then
        sha256sum "$file"
    elif command -v shasum >/dev/null 2>&1; then
        shasum -a 256 "$file"
    else
        printf 'ERRO: sha256sum/shasum nao encontrado para gerar checksum.\n' >&2
        return 1
    fi
}

db_sha256_check() {
    local file="$1"
    if command -v sha256sum >/dev/null 2>&1; then
        sha256sum -c "$file"
    elif command -v shasum >/dev/null 2>&1; then
        shasum -a 256 -c "$file"
    else
        printf 'ERRO: sha256sum/shasum nao encontrado para validar checksum.\n' >&2
        return 1
    fi
}

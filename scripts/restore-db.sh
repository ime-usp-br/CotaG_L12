#!/usr/bin/env bash
# =============================================================================
# CotaG - Restore padronizado do banco externo
# =============================================================================
# Uso: restore-db.sh <arquivo.sql.gz|arquivo.sql> [--confirm-prod]
#
# - Valida existencia, extensao e checksum (quando disponivel).
# - Exige confirmacao explicita; em APP_ENV=prod exige --confirm-prod.
# - Nunca apaga o backup apos o restore.
# - Le DB_* (ou DB_URL/DATABASE_URL) do ambiente e nunca imprime senha.
# =============================================================================

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib-db.sh
source "${SCRIPT_DIR}/lib-db.sh"

FILE=""
CONFIRM_PROD=0

usage() {
    cat <<'EOF'
Uso: restore-db.sh <arquivo.sql.gz|arquivo.sql> [--confirm-prod]

O restaurante e destrutivo: sobrescreve objetos existentes no banco alvo.
Em APP_ENV=prod (ou production) o argumento --confirm-prod e obrigatorio.
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --help|-h)
            usage
            exit 0
            ;;
        --confirm-prod)
            CONFIRM_PROD=1
            shift
            ;;
        -*)
            printf 'ERRO: argumento desconhecido: %s\n' "$1" >&2
            usage >&2
            exit 2
            ;;
        *)
            if [[ -n "$FILE" ]]; then
                printf 'ERRO: informe apenas um arquivo de backup.\n' >&2
                exit 2
            fi
            FILE="$1"
            shift
            ;;
    esac
done

if [[ -z "$FILE" ]]; then
    usage >&2
    exit 2
fi

if [[ ! -f "$FILE" ]]; then
    printf 'ERRO: arquivo nao encontrado: %s\n' "$FILE" >&2
    exit 1
fi

case "$FILE" in
    *.sql.gz|*.sql)
        ;;
    *)
        printf 'ERRO: extensao invalida. Use .sql.gz ou .sql.\n' >&2
        exit 1
        ;;
esac

command -v gzip >/dev/null 2>&1 || {
    printf 'ERRO: gzip nao encontrado.\n' >&2
    exit 1
}

if [[ "$FILE" == *.gz ]]; then
    gzip -t "$FILE"
fi

CHECKSUM_FILE="${FILE}.sha256"
if [[ -f "$CHECKSUM_FILE" ]]; then
    printf 'Validando checksum...\n'
    (
        cd "$(dirname "$FILE")"
        db_sha256_check "$(basename "$CHECKSUM_FILE")"
    )
else
    printf 'AVISO: checksum %s nao encontrado; prosseguindo sem validacao.\n' "$CHECKSUM_FILE" >&2
fi

db_load_env
db_validate_required
db_require_credentials

ENGINE="$(db_engine)"
ENV_LOWER="${APP_ENV:-}"

if [[ "$ENV_LOWER" == "prod" || "$ENV_LOWER" == "production" ]]; then
    if [[ "$CONFIRM_PROD" -ne 1 ]]; then
        printf 'ERRO: APP_ENV=%s exige o argumento --confirm-prod.\n' "$APP_ENV" >&2
        exit 1
    fi
fi

cat <<EOF
================================================================================
ATENCAO: RESTORE DESTRUTIVO
================================================================================
Arquivo : $FILE
Banco   : $DB_HOST:$DB_PORT/$DB_DATABASE
Engine  : $ENGINE
Ambiente: ${APP_ENV:-nao definido}

O conteudo atual do banco pode ser sobrescrito. O backup NAO sera apagado.
================================================================================
EOF

if [[ "$ENV_LOWER" == "prod" || "$ENV_LOWER" == "production" ]]; then
    read -r -p "Digite RESTORE-PROD para confirmar: " ANSWER
    if [[ "$ANSWER" != "RESTORE-PROD" ]]; then
        printf 'Restore cancelado.\n' >&2
        exit 1
    fi
else
    read -r -p "Digite RESTORE para confirmar: " ANSWER
    if [[ "$ANSWER" != "RESTORE" ]]; then
        printf 'Restore cancelado.\n' >&2
        exit 1
    fi
fi

printf 'Validando conexao com %s:%s/%s...\n' "$DB_HOST" "$DB_PORT" "$DB_DATABASE"
db_check_connection

printf 'Restaurando %s...\n' "$FILE"

case "$ENGINE" in
    mysql)
        command -v mysql >/dev/null 2>&1 || {
            printf 'ERRO: cliente mysql nao encontrado. Rode no host com mysql-client ou no container da aplicacao.\n' >&2
            exit 1
        }
        if [[ "$FILE" == *.gz ]]; then
            gzip -dc "$FILE"
        else
            cat "$FILE"
        fi | MYSQL_PWD="$DB_PASSWORD" mysql \
            --protocol=TCP \
            --default-character-set=utf8mb4 \
            -h "$DB_HOST" \
            -P "$DB_PORT" \
            -u "$DB_USERNAME" \
            "$DB_DATABASE"
        ;;
    postgres)
        command -v psql >/dev/null 2>&1 || {
            printf 'ERRO: cliente psql nao encontrado. Rode no host com postgresql-client ou no container da aplicacao.\n' >&2
            exit 1
        }
        if [[ "$FILE" == *.gz ]]; then
            gzip -dc "$FILE"
        else
            cat "$FILE"
        fi | PGPASSWORD="$DB_PASSWORD" psql \
            -h "$DB_HOST" \
            -p "$DB_PORT" \
            -U "$DB_USERNAME" \
            -d "$DB_DATABASE" \
            -v ON_ERROR_STOP=1
        ;;
esac

printf 'Restore concluido. O backup foi preservado em %s.\n' "$FILE"

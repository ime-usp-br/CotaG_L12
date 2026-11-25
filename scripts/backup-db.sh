#!/usr/bin/env bash
# =============================================================================
# CotaG - Backup padronizado do banco externo
# =============================================================================
# Gera <projeto>-<env>-<db>-YYYYmmdd-HHMMSS.sql.gz + .sha256 em BACKUP_DIR.
# Le DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE/DB_NAME,
# DB_USERNAME/DB_USER e DB_PASSWORD/DB_PASS (ou DB_URL/DATABASE_URL).
#
# Variaveis opcionais:
#   PROJECT_NAME            nome do projeto (padrao cotag)
#   BACKUP_DIR              diretorio de destino (padrao backups)
#   BACKUP_RETENTION_DAYS   dias de retencao; 0 desabilita (padrao 7)
#
# Nunca imprime senha. Use MYSQL_PWD/PGPASSWORD, nunca -p<senha>.
# =============================================================================

set -Eeuo pipefail
umask 077

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib-db.sh
source "${SCRIPT_DIR}/lib-db.sh"

usage() {
    cat <<'EOF'
Uso: backup-db.sh

Le as variaveis DB_* (ou DB_URL/DATABASE_URL) do ambiente.
Gera um dump .sql.gz com checksum .sha256 em BACKUP_DIR.
EOF
}

if [[ "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
    usage
    exit 0
fi

db_load_env
db_validate_required
db_require_credentials

ENGINE="$(db_engine)"
BACKUP_DIR="${BACKUP_DIR:-backups}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-7}"

if ! [[ "$RETENTION_DAYS" =~ ^[0-9]+$ ]]; then
    printf 'ERRO: BACKUP_RETENTION_DAYS=%s invalido. Use um inteiro >= 0.\n' "$RETENTION_DAYS" >&2
    exit 1
fi

sanitize() {
    printf '%s' "${1:-unknown}" \
        | tr '[:upper:]' '[:lower:]' \
        | tr -c 'a-z0-9._-' '-' \
        | sed -e 's/-\{2,\}/-/g' -e 's/^-//' -e 's/-$//'
}

PROJECT_SAFE="$(sanitize "${PROJECT_NAME:-cotag}")"
ENV_SAFE="$(sanitize "${APP_ENV:-unknown}")"
DB_SAFE="$(sanitize "${DB_DATABASE}")"

mkdir -p "$BACKUP_DIR"

TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
FILE="${BACKUP_DIR}/${PROJECT_SAFE}-${ENV_SAFE}-${DB_SAFE}-${TIMESTAMP}.sql.gz"
if [[ -e "$FILE" ]]; then
    FILE="${BACKUP_DIR}/${PROJECT_SAFE}-${ENV_SAFE}-${DB_SAFE}-${TIMESTAMP}-$$.sql.gz"
fi

TMP_FILE="${FILE}.part"
CHECKSUM_FILE="${FILE}.sha256"
TMP_CHECKSUM="${CHECKSUM_FILE}.part"

cleanup() {
    rm -f "$TMP_FILE" "$TMP_CHECKSUM"
}
trap cleanup EXIT

case "$ENGINE" in
    mysql)
        command -v mysqldump >/dev/null 2>&1 || {
            printf 'ERRO: mysqldump nao encontrado. Rode no host com mysql-client ou no container da aplicacao.\n' >&2
            exit 1
        }
        ;;
    postgres)
        command -v pg_dump >/dev/null 2>&1 || {
            printf 'ERRO: pg_dump nao encontrado. Rode no host com postgresql-client ou no container da aplicacao.\n' >&2
            exit 1
        }
        ;;
esac

command -v gzip >/dev/null 2>&1 || {
    printf 'ERRO: gzip nao encontrado.\n' >&2
    exit 1
}

printf 'Validando conexao com %s:%s/%s...\n' "$DB_HOST" "$DB_PORT" "$DB_DATABASE"
db_check_connection

printf 'Gerando backup (%s)...\n' "$ENGINE"

case "$ENGINE" in
    mysql)
        DUMP_OPTS=(
            --single-transaction
            --quick
            --routines
            --triggers
            --events
            --default-character-set=utf8mb4
        )
        # grep -c evita SIGPIPE em mysqldump --help quando pipefail esta ativo.
        if mysqldump --help 2>/dev/null | grep -c -- '--no-tablespaces' >/dev/null; then
            DUMP_OPTS+=(--no-tablespaces)
        fi

        MYSQL_PWD="$DB_PASSWORD" mysqldump \
            --protocol=TCP \
            -h "$DB_HOST" \
            -P "$DB_PORT" \
            -u "$DB_USERNAME" \
            "${DUMP_OPTS[@]}" \
            "$DB_DATABASE" \
            | gzip -9 > "$TMP_FILE"
        ;;
    postgres)
        PGPASSWORD="$DB_PASSWORD" pg_dump \
            -h "$DB_HOST" \
            -p "$DB_PORT" \
            -U "$DB_USERNAME" \
            -d "$DB_DATABASE" \
            --no-owner \
            --no-privileges \
            --clean \
            --if-exists \
            --format=plain \
            | gzip -9 > "$TMP_FILE"
        ;;
esac

if [[ ! -s "$TMP_FILE" ]]; then
    printf 'ERRO: dump vazio; backup abortado.\n' >&2
    exit 1
fi

gzip -t "$TMP_FILE"
mv "$TMP_FILE" "$FILE"

BASE_NAME="$(basename "$FILE")"
(
    cd "$BACKUP_DIR"
    db_sha256_create "$BASE_NAME"
) > "$TMP_CHECKSUM"

if [[ ! -s "$TMP_CHECKSUM" ]]; then
    printf 'ERRO: nao foi possivel gerar o checksum de %s. O dump foi preservado sem checksum.\n' "$FILE" >&2
    rm -f "$TMP_CHECKSUM"
    exit 1
fi

mv "$TMP_CHECKSUM" "$CHECKSUM_FILE"

if (( RETENTION_DAYS > 0 )); then
    printf 'Aplicando retencao de %s dias...\n' "$RETENTION_DAYS"
    find "$BACKUP_DIR" -maxdepth 1 -type f \
        -name "${PROJECT_SAFE}-${ENV_SAFE}-*.sql.gz" \
        -mtime +"$RETENTION_DAYS" -print -delete
    find "$BACKUP_DIR" -maxdepth 1 -type f \
        -name "${PROJECT_SAFE}-${ENV_SAFE}-*.sql.gz.sha256" \
        -mtime +"$RETENTION_DAYS" -print -delete
fi

printf 'Backup concluido: %s (%s)\n' "$FILE" "$(du -h "$FILE" | cut -f1)"
printf 'Checksum: %s\n' "$CHECKSUM_FILE"

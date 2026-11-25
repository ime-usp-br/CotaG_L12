#!/usr/bin/env bash
# =============================================================================
# CotaG - Espera o banco externo por dominio
# =============================================================================
# Requer DB_HOST/DB_PORT (ou DB_URL/DATABASE_URL) e os demais DB_*.
# Variaveis opcionais:
#   WAIT_FOR_DB_TIMEOUT  segundos de espera (padrao 120)
#   WAIT_FOR_DB_INTERVAL intervalo entre tentativas (padrao 2)
#   DB_TCP_TIMEOUT       timeout de cada tentativa TCP (padrao 5)
# =============================================================================

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib-db.sh
source "${SCRIPT_DIR}/lib-db.sh"

if [[ "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
    cat <<'EOF'
Uso: wait-for-db.sh

Espera o banco externo configurado em DB_HOST/DB_PORT ficar acessivel.
Nao valida usuario/senha; use db-check/migrations para isso.
EOF
    exit 0
fi

db_load_env
db_validate_required
db_wait

#!/usr/bin/env bash
# =============================================================================
# CotaG - Validacao do contrato de deploy
# =============================================================================
# Valida docker-compose.yml e docker-compose.test.yml sem subir containers.
# Nao imprime segredos: usa valores ficticios apenas para interpolar o compose.
# =============================================================================

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
cd "$ROOT_DIR"

command -v docker >/dev/null 2>&1 || {
    printf 'ERRO: docker nao encontrado; nao e possivel validar os composes.\n' >&2
    exit 1
}

TMP_ENV="$(mktemp)"
TMP_APP="$(mktemp)"
TMP_TEST="$(mktemp)"
trap 'rm -f "$TMP_ENV" "$TMP_APP" "$TMP_TEST"' EXIT

cat > "$TMP_ENV" <<'EOF'
APP_ENV=dev
APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
APP_URL=https://dev.example.org
DB_CONNECTION=mysql
DB_HOST=db-dev.example.org
DB_PORT=3306
DB_DATABASE=cotag
DB_USERNAME=cotag_user
DB_PASSWORD=validation-only
EOF

FAILED=0
ok() { printf 'OK   %s\n' "$1"; }
bad() { printf 'FALHA %s\n' "$1"; FAILED=1; }

check_absent() {
    local label="$1" pattern="$2" file="$3"
    if grep -Eq -- "$pattern" "$file"; then bad "$label"; else ok "$label"; fi
}

check_present() {
    local label="$1" pattern="$2" file="$3"
    if grep -Eq -- "$pattern" "$file"; then ok "$label"; else bad "$label"; fi
}

printf 'Validando docker-compose.yml...\n'
if ! docker compose --env-file "$TMP_ENV" -f docker-compose.yml config > "$TMP_APP"; then
    printf 'ERRO: docker compose config falhou para docker-compose.yml.\n' >&2
    exit 1
fi

check_absent  'app stack sem container_name' '^[[:space:]]*container_name:' "$TMP_APP"
check_absent  'app stack sem env_file' '^[[:space:]]*env_file:' "$TMP_APP"
check_absent  'app stack sem ports publicadas' '^[[:space:]]*ports:' "$TMP_APP"
check_absent  'app stack sem bind mounts de codigo' 'type:[[:space:]]*bind' "$TMP_APP"
check_absent  'app stack sem servico de banco' '^  (db|database|mysql|mariadb|postgres|postgresql):[[:space:]]*$' "$TMP_APP"
check_absent  'app stack sem rede db' '^  (db|database):[[:space:]]*$' "$TMP_APP"
if grep -Eq '^[[:space:]]*depends_on:' "$TMP_APP"; then
    if grep -A8 '^[[:space:]]*depends_on:' "$TMP_APP" | grep -Eq '^[[:space:]]+(db|database|mysql|mariadb|postgres|postgresql):'; then
        bad 'app stack sem depends_on de banco'
    else
        ok 'app stack sem depends_on de banco'
    fi
else
    ok 'app stack sem depends_on de banco'
fi
check_absent  'app stack sem host.docker.internal' 'host\.docker\.internal' "$TMP_APP"
check_present 'app stack usa rede web externa' 'external: true' "$TMP_APP"
check_present 'app stack usa alias cotag-dev-web' 'cotag-dev-web' "$TMP_APP"
check_present 'app stack declara expose' '^[[:space:]]*expose:' "$TMP_APP"
check_present 'app stack usa healthcheck' '^[[:space:]]*healthcheck:' "$TMP_APP"

if awk '/^  worker:/{f=1; next} f && /^  [a-zA-Z0-9_-]+:/{f=0} f {print}' "$TMP_APP" | grep -Eq '^[[:space:]]+web:'; then
    bad 'worker sem rede web'
else
    ok 'worker sem rede web'
fi

if grep -Eq 'APP_KEY:[[:space:]]*"\$\{APP_KEY:\?' docker-compose.yml \
    && grep -Eq 'APP_ENV:[[:space:]]*"\$\{APP_ENV:\?' docker-compose.yml \
    && grep -Eq 'DB_HOST:[[:space:]]*"\$\{DB_HOST:\?' docker-compose.yml \
    && grep -Eq 'DB_PASSWORD:[[:space:]]*"\$\{DB_PASSWORD:\?' docker-compose.yml; then
    ok 'variaveis criticas usam ${VAR:?}'
else
    bad 'variaveis criticas usam ${VAR:?}'
fi

printf 'Validando docker-compose.test.yml...\n'
if ! docker compose -f docker-compose.test.yml config > "$TMP_TEST"; then
    printf 'ERRO: docker compose config falhou para docker-compose.test.yml.\n' >&2
    exit 1
fi

check_absent  'test stack sem ports publicadas' '^[[:space:]]*ports:' "$TMP_TEST"
check_absent  'test stack sem bind mounts de codigo' 'type:[[:space:]]*bind' "$TMP_TEST"
check_absent  'test stack sem rede web' '^  web:[[:space:]]*$' "$TMP_TEST"
check_absent  'test stack sem env_file' '^[[:space:]]*env_file:' "$TMP_TEST"
check_present 'test stack isolada por nome' '^name:[[:space:]]*cotag-test$' "$TMP_TEST"
check_present 'test stack tem banco efemero' '^  test-db:' "$TMP_TEST"
check_present 'test stack tem rede interna' 'internal: true' "$TMP_TEST"

if awk '/^  test:/{f=1; next} f && /^  [a-zA-Z0-9_-]+:/{f=0} f {print}' "$TMP_TEST" | grep -Eq '^[[:space:]]+web:'; then
    bad 'test service sem rede web'
else
    ok 'test service sem rede web'
fi

if grep -Eq '^[[:space:]]+test:[[:space:]]*' "$TMP_TEST"; then
    ok 'test service usa apenas rede test'
else
    bad 'test service usa apenas rede test'
fi

if (( FAILED != 0 )); then
    printf '\nFalha na validacao do contrato de deploy.\n' >&2
    exit 1
fi

printf '\nContrato de deploy validado com sucesso.\n'

#!/usr/bin/env bash
# =============================================================================
# CotaG - Healthcheck da aplicacao
# =============================================================================
# Valida o endpoint real do Laravel (/up por padrao) dentro do container.
# Variaveis opcionais:
#   WEB_PORT             porta interna do Nginx/PHP-FPM (padrao 8080)
#   HEALTHCHECK_HOST     host do healthcheck (padrao 127.0.0.1)
#   HEALTHCHECK_PATH     caminho do endpoint (padrao /up)
#   HEALTHCHECK_TIMEOUT  timeout em segundos (padrao 5)
# =============================================================================

set -Eeuo pipefail

HOST="${HEALTHCHECK_HOST:-127.0.0.1}"
PORT="${WEB_PORT:-8080}"
PATHNAME="${HEALTHCHECK_PATH:-/up}"
TIMEOUT="${HEALTHCHECK_TIMEOUT:-5}"
URL="http://${HOST}:${PORT}${PATHNAME}"

if ! [[ "$PORT" =~ ^[0-9]+$ ]] || (( PORT < 1 || PORT > 65535 )); then
    printf 'ERRO: WEB_PORT=%s invalido.\n' "$PORT" >&2
    exit 1
fi

if command -v curl >/dev/null 2>&1; then
    curl -fsS --max-time "$TIMEOUT" "$URL" >/dev/null
    exit 0
fi

if command -v wget >/dev/null 2>&1; then
    wget -q -O /dev/null --timeout="$TIMEOUT" "$URL"
    exit 0
fi

if command -v php >/dev/null 2>&1; then
    php -r '
        $url = $argv[1];
        $timeout = (int) $argv[2];
        $ctx = stream_context_create(["http" => ["timeout" => $timeout]]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            fwrite(STDERR, "ERRO: healthcheck falhou em {$url}\n");
            exit(1);
        }
    ' "$URL" "$TIMEOUT"
    exit 0
fi

printf 'ERRO: curl, wget ou php nao encontrados para o healthcheck.\n' >&2
exit 1

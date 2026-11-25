# =============================================================================
# CotaG - Operacoes locais padronizadas
# =============================================================================
# O banco NAO pertence a esta stack: nao existem alvos db-up/db-down.
# As variaveis vem do ambiente exportado ou de um .env local opcional.
# No Portainer, use o campo de variaveis da stack; nao suba .env no servidor.
# =============================================================================

SHELL := /bin/bash
COMPOSE ?= docker compose
COMPOSE_TEST := $(COMPOSE) -f docker-compose.test.yml
COMPOSE_PROJECT_NAME ?= cotag
export COMPOSE_PROJECT_NAME

LOAD_ENV = set -a; if [ -f .env ]; then . ./.env; fi; set +a;

.PHONY: help up down build migrate seed test logs ps health config prod-check shell db-check backup-db restore-db

help:
	@printf '%s\n' \
		'CotaG - alvos disponiveis:' \
		'  make up             sobe a stack unica (app + worker)' \
		'  make down           derruba a stack (preserva o volume de storage)' \
		'  make build          constroi a imagem com --pull' \
		'  make migrate        roda migrations no container app' \
		'  make seed           roda seeders (bloqueado em producao)' \
		'  make test           roda a suite isolada em docker-compose.test.yml' \
		'  make logs           acompanha logs de app e worker' \
		'  make ps             status dos servicos' \
		'  make health         valida o healthcheck dentro do container' \
		'  make config         valida os dois composes' \
		'  make prod-check     valida o contrato de deploy' \
		'  make shell          shell no container app' \
		'  make db-check       valida a conexao com o banco externo' \
		'  make backup-db      executa scripts/backup-db.sh' \
		'  make restore-db FILE=... [ARGS=--confirm-prod]  executa o restore'

up:
	@$(LOAD_ENV) $(COMPOSE) up -d --build

down:
	@$(LOAD_ENV) $(COMPOSE) down

build:
	@$(LOAD_ENV) $(COMPOSE) build --pull

migrate:
	@$(LOAD_ENV) $(COMPOSE) exec -T app php artisan migrate --force --no-interaction

seed:
	@$(LOAD_ENV) \
	if [ "$${APP_ENV:-}" = "prod" ] || [ "$${APP_ENV:-}" = "production" ]; then \
		echo "ERRO: seeders bloqueados em producao (APP_ENV=$${APP_ENV:-})."; \
		exit 1; \
	fi; \
	$(COMPOSE) exec -T app php artisan db:seed --force --no-interaction

test:
	@status=0; \
	$(COMPOSE_TEST) up --build --abort-on-container-exit --exit-code-from test || status=$$?; \
	$(COMPOSE_TEST) down -v; \
	exit $$status

logs:
	@$(LOAD_ENV) $(COMPOSE) logs -f --tail=200

ps:
	@$(LOAD_ENV) $(COMPOSE) ps

health:
	@$(LOAD_ENV) $(COMPOSE) exec -T app /var/www/html/scripts/healthcheck.sh

config:
	@$(LOAD_ENV) $(COMPOSE) config -q
	@$(COMPOSE_TEST) config -q

prod-check:
	@./scripts/prod-check.sh

shell:
	@$(LOAD_ENV) $(COMPOSE) exec app bash

db-check:
	@$(LOAD_ENV) $(COMPOSE) exec -T app php artisan db:show

backup-db:
	@$(LOAD_ENV) ./scripts/backup-db.sh

restore-db:
	@if [ -z "$(FILE)" ]; then \
		echo "Uso: make restore-db FILE=backups/arquivo.sql.gz [ARGS=--confirm-prod]"; \
		exit 1; \
	fi; \
	$(LOAD_ENV) ./scripts/restore-db.sh $(ARGS) "$(FILE)"

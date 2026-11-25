# Deploy CotaG - Portainer + Nginx Proxy Manager

**Versao:** 2.0.0
**Data:** 2026-11-25
**Branch alvo:** `main`

Este documento e o guia operacional do deploy do CotaG em **dev**,
**homologacao** e **producao**. A aplicacao e a mesma nos tres ambientes; o que
muda sao apenas as variaveis configuradas no Portainer.

> **Producao atual:** a producao existente esta na branch `main`, que tambem e a
> branch alvo. Ela usa a stack legada `cotag` (`docker-compose.prod.yml`,
> containers `cotag-app`/`cotag-worker`, NPM apontando para `cotag-app:80`).
> Essa stack **nao foi alterada** por esta padronizacao. O cutover operacional
> para a stack canonica esta descrito na secao 13.

---

## 1. Arquitetura

```
Internet
   |
   v
Nginx Proxy Manager (NPM)  -- rede externa "web"
   |  http://cotag-<env>-web:8080
   v
cotag-<env>-app  (Nginx + PHP-FPM no mesmo container)
   |
   +--> Banco externo por dominio: db-dev.<dominio>, db-homolog.<dominio>, db.<dominio>
   +--> Replicado USP (host/porta fornecidos pela infra)
   +--> SMTP/integracoes externas

cotag-<env>-worker  (mesma imagem, CONTAINER_ROLE=worker)
   |
   +--> Banco externo por dominio
   +--> Replicado/SMTP conforme a fila
```

Principios:

- O NPM e o unico ponto de exposicao HTTP/HTTPS.
- A aplicacao nao publica portas no host.
- O banco e externo a stack da aplicacao e nao usa a rede `web`.
- O worker nao usa a rede `web`.
- Nao existe rede Docker `db`, servico `db` ou `docker-compose.db.yml`.
- Segredos ficam no Portainer (ou em secret manager futuro), nunca no Git.
- Logs vao para stdout/stderr.

---

## 2. Arquivos do contrato

| Arquivo | Papel |
|---------|-------|
| `docker-compose.yml` | Stack unica da aplicacao (dev, homologacao e producao) |
| `docker-compose.test.yml` | Stack isolada de testes, com MySQL efemero em `tmpfs` |
| `.env.example` | Contrato unico de variaveis, copiavel para o Portainer |
| `Makefile` | Operacoes locais padronizadas |
| `docker/production/Dockerfile` | Imagem multi-stage (runtime + stage de testes) |
| `docker/production/entrypoint.sh` | Bootstrap idempotente do container |
| `docker/production/nginx.conf` | Template do Nginx interno |
| `docker/production/php-fpm.conf` | Pool PHP-FPM |
| `docker/production/supervisord*.conf` | Processos do app e do worker |
| `scripts/lib-db.sh` | Helpers compartilhados de banco externo |
| `scripts/wait-for-db.sh` | Espera o banco por dominio |
| `scripts/healthcheck.sh` | Valida o endpoint real `/up` |
| `scripts/backup-db.sh` | Backup do banco do projeto |
| `scripts/restore-db.sh` | Restore do banco do projeto |
| `scripts/prod-check.sh` | Valida o contrato de deploy |
| `docs/deploy/_legacy/` | Arquivos antigos arquivados (nao usar) |

Nao fazem parte do contrato: `docker-compose.prod.yml`,
`docker-compose.prod.local.yml`, `docker-compose.dev.yml`, `docker-compose.db.yml`,
servico `db`, rede `db` e documento de mapeamento de branches.

---

## 3. Ambientes

Os nomes reais de dominio, DNS, host do NPM e stacks da infraestrutura **nao
foram identificados no repositorio**. Preencha a tabela abaixo com os dados
fornecidos pela infraestrutura.

| Ambiente | Branch alvo | Stack Portainer | Compose path | Banco (dominio) | NPM Proxy Host | Dominio publico |
|----------|-------------|-----------------|--------------|-----------------|----------------|-----------------|
| dev | `main` | `cotag-dev` | `docker-compose.yml` | `db-dev.<dominio>` | `dev.<dominio>` | `https://dev.<dominio>` |
| homolog | `main` | `cotag-homolog` | `docker-compose.yml` | `db-homolog.<dominio>` | `homolog.<dominio>` | `https://homolog.<dominio>` |
| prod | `main` | `cotag-prod` | `docker-compose.yml` | `db.<dominio>` | `<dominio>` | `https://<dominio>` |

O alias interno do app e sempre `cotag-<APP_ENV>-web`:

- dev: `cotag-dev-web:8080`
- homolog: `cotag-homolog-web:8080`
- prod: `cotag-prod-web:8080`

> O alias e derivado da variavel `APP_ENV`; por isso os valores devem ser
> exatamente `dev`, `homolog` ou `prod`.

### Producao temporaria / legada

- Branch: `main` (mesma branch alvo; nao ha excecao de branch).
- Stack antiga: `cotag`, arquivo `docker-compose.prod.yml`, containers
  `cotag-app`/`cotag-worker`, NPM para `cotag-app:80`, bind mount `./storage`.
- Essa stack **nao foi alterada**. O cutover para a stack canonica e operacional
  (stack/NPM/volume), nao de branch.

---

## 4. Variaveis de ambiente

O contrato unico esta em `.env.example`. No Portainer, use o campo de variaveis
da stack; nao suba `.env` no servidor. Os nomes sao iguais nos tres ambientes;
apenas os valores mudam.

### 4.1 Tabela de variaveis

| Variavel | Obrigatoria | dev | homolog | prod | Secreta | Observacoes |
|----------|-------------|-----|---------|------|---------|-------------|
| `APP_NAME` | nao | `CotaG` | `CotaG` | `CotaG` | nao | Nome exibido |
| `APP_ENV` | sim | `dev` | `homolog` | `prod` | nao | Mapeado para o Laravel em `config/app.php` |
| `APP_DEBUG` | nao | `true` | `false` | `false` | nao | Nunca `true` em homolog/prod |
| `APP_KEY` | sim | chave dev | chave homolog | chave prod | **sim** | Gere uma por ambiente; nao reutilize |
| `APP_URL` | sim | `https://dev.<dominio>` | `https://homolog.<dominio>` | `https://<dominio>` | nao | URL publica com HTTPS |
| `APP_VERSION` | nao | tag/imagem | tag/imagem | tag/imagem | nao | Ex.: `1.0.0`; ajuda no rollback |
| `APP_LOCALE` | nao | `pt_BR` | `pt_BR` | `pt_BR` | nao | |
| `APP_FALLBACK_LOCALE` | nao | `pt_BR` | `pt_BR` | `pt_BR` | nao | |
| `APP_FAKER_LOCALE` | nao | `pt_BR` | `pt_BR` | `pt_BR` | nao | |
| `APP_MAINTENANCE_DRIVER` | nao | `file` | `file` | `file` | nao | |
| `APP_ENV_NATIVE` | nao | vazio | vazio | vazio | nao | Override opcional do valor nativo do Laravel |
| `TZ` | nao | `America/Sao_Paulo` | `America/Sao_Paulo` | `America/Sao_Paulo` | nao | |
| `WEB_PORT` | nao | `8080` | `8080` | `8080` | nao | Porta interna; NPM encaminha para ela |
| `LOG_CHANNEL` | nao | `stderr` | `stderr` | `stderr` | nao | Logs em stdout/stderr |
| `LOG_STACK` | nao | `stderr` | `stderr` | `stderr` | nao | |
| `LOG_LEVEL` | nao | `debug` | `warning` | `warning` | nao | |
| `DB_CONNECTION` | sim | `mysql` | `mysql` | `mysql` | nao | `mysql`, `mariadb` ou `pgsql` |
| `DB_HOST` | sim | `db-dev.<dominio>` | `db-homolog.<dominio>` | `db.<dominio>` | nao | **Dominio DNS**, nunca `db`/`mysql`/`localhost` |
| `DB_PORT` | sim | `3306` | `3306` | `3306` | nao | Porta do banco externo |
| `DB_DATABASE` | sim | `cotag` | `cotag` | `cotag` | nao | Nome definido pela infra |
| `DB_USERNAME` | sim | usuario dev | usuario homolog | usuario prod | nao | Definido pela infra |
| `DB_PASSWORD` | sim | senha dev | senha homolog | senha prod | **sim** | Definida pela infra |
| `DB_URL` | nao | vazio | vazio | vazio | **sim** | URL unica opcional lida pelo Laravel |
| `DATABASE_URL` | nao | vazio | vazio | vazio | **sim** | URL unica opcional aceita pelos scripts |
| `SESSION_DRIVER` | nao | `database` | `database` | `database` | nao | |
| `SESSION_SECURE_COOKIE` | nao | `true` | `true` | `true` | nao | `false` apenas em HTTP local |
| `SESSION_SAME_SITE` | nao | `lax` | `lax` | `lax` | nao | |
| `CACHE_STORE` | nao | `database` | `database` | `database` | nao | Pode ser `redis` se houver |
| `QUEUE_CONNECTION` | nao | `database` | `database` | `database` | nao | Worker usa esta conexao |
| `BROADCAST_CONNECTION` | nao | `log` | `log` | `log` | nao | |
| `FILESYSTEM_DISK` | nao | `local` | `local` | `local` | nao | `s3` se configurado |
| `REDIS_URL` | nao | vazio | vazio | vazio | **sim** | Apenas se usar Redis |
| `REDIS_HOST` | nao | `127.0.0.1` | `127.0.0.1` | `127.0.0.1` | nao | Ajuste se usar Redis externo |
| `REDIS_PASSWORD` | nao | `null` | `null` | `null` | **sim** | |
| `REDIS_PORT` | nao | `6379` | `6379` | `6379` | nao | |
| `MAIL_MAILER` | nao | `log` | `smtp` | `smtp` | nao | |
| `MAIL_HOST` | nao | `127.0.0.1` | host SMTP | host SMTP | nao | |
| `MAIL_PORT` | nao | `587` | `587` | `587` | nao | |
| `MAIL_USERNAME` | nao | vazio | usuario SMTP | usuario SMTP | **sim** | |
| `MAIL_PASSWORD` | nao | vazio | senha SMTP | senha SMTP | **sim** | |
| `MAIL_FROM_ADDRESS` | nao | `noreply@example.org` | `noreply@<dominio>` | `noreply@<dominio>` | nao | |
| `MAIL_FROM_NAME` | nao | `CotaG` | `CotaG` | `CotaG` | nao | |
| `SENHAUNICA_KEY` | funcional | chave dev | chave homolog | chave prod | **sim** | OAuth USP |
| `SENHAUNICA_SECRET` | funcional | segredo dev | segredo homolog | segredo prod | **sim** | OAuth USP |
| `SENHAUNICA_CALLBACK_ID` | funcional | callback dev | callback homolog | callback prod | **sim** | OAuth USP |
| `REPLICADO_HOST` | funcional | host dev | host homolog | host prod | nao | Replicado USP |
| `REPLICADO_PORT` | funcional | `1433` | `1433` | `1433` | nao | |
| `REPLICADO_DATABASE` | funcional | `replicado` | `replicado` | `replicado` | nao | |
| `REPLICADO_USERNAME` | funcional | usuario | usuario | usuario | **sim** | |
| `REPLICADO_PASSWORD` | funcional | senha | senha | senha | **sim** | |
| `REPLICADO_CODUNDCLG` | funcional | codigo | codigo | codigo | nao | Codigo da unidade |
| `REPLICADO_SYBASE` | nao | `true` | `true` | `true` | nao | Encoding legado |
| `RUN_MIGRATIONS` | nao | `true` | `true` | `true` | nao | Somente no app |
| `RUN_SEEDERS` | nao | `true` | `false` | `false` | nao | Proibido em prod |
| `WARM_CACHE` | nao | `false` | `true` | `true` | nao | Config/view/Filament |
| `PROJECT_NAME` | nao | `cotag` | `cotag` | `cotag` | nao | Prefixo dos backups |
| `BACKUP_DIR` | nao | `backups` | `backups` | `backups` | nao | No Portainer use `/var/www/html/storage/backups` |
| `BACKUP_RETENTION_DAYS` | nao | `7` | `7` | `7` | nao | `0` desabilita a retencao |
| `WWWUSER` / `WWWGROUP` | nao | `1000` | `1000` | `1000` | nao | Apenas build |
| `CONTAINER_ROLE` | automatico | `app`/`worker` | `app`/`worker` | `app`/`worker` | nao | Definido pelo compose |

### 4.2 Valores esperados por ambiente

| Variavel | dev | homolog | prod |
|----------|-----|---------|------|
| `APP_ENV` | `dev` | `homolog` | `prod` |
| `APP_DEBUG` | `true` | `false` | `false` |
| `DB_HOST` | `db-dev.<dominio>` | `db-homolog.<dominio>` | `db.<dominio>` |
| `RUN_MIGRATIONS` | `true` | `true` | `true` |
| `RUN_SEEDERS` | `true` | `false` | `false` |
| `WARM_CACHE` | `false` | `true` | `true` |
| `LOG_LEVEL` | `debug` | `warning` | `warning` |
| `SESSION_SECURE_COOKIE` | `true` | `true` | `true` |

### 4.3 Gerar o `APP_KEY`

Gere uma chave por ambiente e guarde no Portainer. Nao reutilize a mesma chave
entre ambientes.

```bash
docker run --rm cotag:latest php artisan key:generate --show
```

Copie a saida (`base64:...`) para `APP_KEY` da stack. O entrypoint consegue
gerar e persistir uma chave em `storage/app/app_key` quando `APP_KEY` esta
ausente, mas isso e apenas fallback local: em Portainer, defina `APP_KEY`
explicitamente para que app e worker compartilhem a mesma chave e para que uma
recriacao de volume nao invalide sessoes/dados criptografados.

---

## 5. Mapeamento do ambiente para o Laravel

O contrato de deploy usa `APP_ENV=dev|homolog|prod`. O Laravel le `APP_ENV`
nativamente, entao `config/app.php` faz o mapeamento:

| `APP_ENV` (deploy) | `config('app.env')` (Laravel) | `APP_DEBUG` esperado |
|--------------------|-------------------------------|----------------------|
| `dev` | `local` | `true` |
| `homolog` | `production` | `false` |
| `prod` | `production` | `false` |
| `local` (legado) | `local` | conforme ambiente |
| `production` (legado) | `production` | `false` |

Se precisar forcar o valor nativo, defina `APP_ENV_NATIVE` (por exemplo,
`APP_ENV_NATIVE=production`). Fora isso, deixe vazio.

---

## 6. Checklist do Portainer

1. **Rede externa `web`**: crie uma vez, se ainda nao existir.
   ```bash
   docker network create web
   ```
2. **Stack da aplicacao**: `Stacks -> Add stack -> Repository`.
   - Repository: `https://github.com/ime-usp-br/CotaG_L12`
   - Branch: `main`
   - Compose path: `docker-compose.yml`
   - Nome da stack: `cotag-dev`, `cotag-homolog` ou `cotag-prod`.
3. **Variaveis**: copie as chaves de `.env.example` para o campo de variaveis
   da stack. Nao suba `.env` no servidor.
4. **Banco**: configure `DB_HOST` com o dominio do banco daquele ambiente
   (`db-dev.<dominio>`, `db-homolog.<dominio>` ou `db.<dominio>`).
5. **Segredos**: preencha `APP_KEY`, `DB_PASSWORD`, `SENHAUNICA_*`,
   `REPLICADO_PASSWORD` e `MAIL_PASSWORD` apenas no Portainer.
6. **Deploy**: clique em `Deploy the stack`.
7. **NPM**: configure o Proxy Host conforme a secao 7.
8. **Healthcheck**: valide com `make health` ou
   `docker compose exec -T app /var/www/html/scripts/healthcheck.sh`.
9. **Migrations**: o entrypoint roda `RUN_MIGRATIONS=true`; se necessario,
   rode manualmente `make migrate`.
10. **Smoke test**: acesse `/`, `/admin` e `/up` pelo dominio publico; registre
    stack, dominio, branch, tag da imagem e data.

> O compose nao tem `container_name`: o nome real do container e gerado pelo
> Portainer. Use o alias estavel `cotag-<env>-web` no NPM e nos testes de rede.

---

## 7. Checklist do NPM

Para cada ambiente, crie um **Proxy Host**:

| Campo | dev | homolog | prod |
|-------|-----|---------|------|
| Domain Names | `dev.<dominio>` | `homolog.<dominio>` | `<dominio>` |
| Scheme | `http` | `http` | `http` |
| Forward Hostname / IP | `cotag-dev-web` | `cotag-homolog-web` | `cotag-prod-web` |
| Forward Port | `8080` | `8080` | `8080` |
| Websockets Support | ON | ON | ON |
| Block Common Exploits | ON | ON | ON |
| SSL | Let's Encrypt/custom | Let's Encrypt/custom | Let's Encrypt/custom |
| Force SSL | ON | ON | ON |
| HTTP/2 | ON | ON | ON |

Configuracoes recomendadas no NPM:

- HSTS: `add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;`
- `client_max_body_size 100M;` (compativel com o Nginx interno)
- Timeouts: `proxy_connect_timeout 60s; proxy_send_timeout 300s; proxy_read_timeout 300s;`
- Headers `X-Forwarded-Proto`, `X-Forwarded-Host`, `X-Forwarded-For` e
  `X-Forwarded-Port` (o NPM envia por padrao; o Laravel confia no proxy em
  `bootstrap/app.php`).
- Nao crie Proxy Host para o banco. O banco tem DNS e porta proprios, fora do
  NPM e fora da rede `web`.

Se aparecer **502 Bad Gateway**:

```bash
docker network inspect web
docker exec <container-do-npm> getent hosts cotag-prod-web
docker exec <container-do-npm> curl -fsS http://cotag-prod-web:8080/up
```

---

## 8. Banco externo por dominio

### 8.1 Responsabilidade

O banco **nao pertence** ao projeto e **nao pertence** a stack da aplicacao. Em
cada ambiente existe uma stack de banco dedicada, compartilhada pelos projetos,
gerenciada pela infraestrutura, com DNS proprio.

A infraestrutura e responsavel por:

- criar o banco, o usuario e as permissoes;
- criar/gerenciar o DNS do banco;
- liberar firewall/rede para a aplicacao;
- executar o backup central;
- monitorar o banco.

O projeto conhece apenas host, porta, nome do banco, usuario e senha.

### 8.2 Como solicitar banco/usuario a infra

Abra um chamado com a infraestrutura contendo:

- projeto: `CotaG`;
- ambiente: `dev`, `homolog` ou `prod`;
- nome do banco desejado (ex.: `cotag`);
- usuario desejado (ex.: `cotag_user`);
- origem da conexao: rede/host onde a stack da aplicacao roda;
- charset/collation: `utf8mb4` / `utf8mb4_unicode_ci`;
- necessidade de acesso de leitura/escrita e DDL para migrations;
- politica de backup/retencao do banco central;
- DNS esperado: `db-dev.<dominio>`, `db-homolog.<dominio>` ou `db.<dominio>`.

Nunca envie senha por issue/chat. A senha deve ser entregue por canal seguro e
configurada apenas no Portainer.

### 8.3 Validar DNS e conexao dentro do container

```bash
# Resolucao DNS do dominio do banco
docker compose exec app getent hosts "$DB_HOST"

# Conexao real (Laravel)
docker compose exec app php artisan db:show

# Espera por dominio/porta
docker compose exec app /var/www/html/scripts/wait-for-db.sh
```

Se o dominio nao resolver dentro do container, a infraestrutura deve fornecer
DNS interno ou um endereco acessivel. **Nao use `extra_hosts` em producao.**

### 8.4 Regras

- `DB_HOST` deve ser um dominio; nunca `db`, `mysql`, `mariadb`, `postgres`,
  `localhost` ou nome de servico Compose.
- Nao use rede Docker compartilhada com o banco.
- Nao use alias de container de banco.
- A aplicacao nao tem `depends_on` de banco.
- O banco nao usa a rede `web`.
- O NPM nao expoe banco.
- O worker declara `depends_on: app: service_started` apenas para evitar
  corrida de criacao do volume nomeado compartilhado. Isso **nao** e uma
  dependencia de banco e o `prod-check` valida essa regra.
- Para testes, use `docker-compose.test.yml` com MySQL efemero e isolado.

---

## 9. Migrations, seeders e cache

O entrypoint executa, somente no container `app`:

- `php artisan migrate --force` quando `RUN_MIGRATIONS=true`;
- `php artisan db:seed --force` quando `RUN_SEEDERS=true` e o ambiente **nao**
  e prod;
- `config:cache`, `view:cache` e `filament:optimize` quando `WARM_CACHE=true`
  e o ambiente nao e dev/local.

O worker **nunca** roda migrations, seeders ou cache warming.

Comandos manuais:

```bash
make migrate
make seed          # bloqueado quando APP_ENV=prod
make shell
php artisan migrate --force
```

> Seeders em producao sao proibidos pelo entrypoint. O `DatabaseSeeder` cria
> usuarios de teste; nao o execute em producao. Para criar o primeiro admin,
> use Senha Unica e atribua a role administrativa conforme o README do projeto.

---

## 10. Healthcheck

O endpoint real e `/up` (definido em `bootstrap/app.php`). O compose usa
`scripts/healthcheck.sh`, que le `WEB_PORT` (padrao `8080`).

```bash
make health
docker compose exec -T app /var/www/html/scripts/healthcheck.sh
docker compose ps
```

O healthcheck valida o boot HTTP da aplicacao. Ele nao substitui monitoramento
de banco, Replicado, SMTP ou fila; esses itens devem ser monitorados pela
infraestrutura/observabilidade.

---

## 11. Backup e restore

### 11.1 Scripts

- `scripts/backup-db.sh`: dump `.sql.gz` + `.sha256`, com retencao.
- `scripts/restore-db.sh`: restore destrutivo com confirmacao e checksum.

Os scripts leem `DB_*` ou `DB_URL`/`DATABASE_URL`, usam `MYSQL_PWD`/`PGPASSWORD`
e nunca imprimem senha. Suportam MySQL/MariaDB e PostgreSQL.

> Para PostgreSQL, a versao major do cliente (`pg_dump`/`psql`) deve ser
> compativel com o servidor. A imagem instala `postgresql-client` 17; se o
> banco for de outra major, ajuste a imagem ou use um cliente compativel. O
> script nao ignora erros de versao (`ON_ERROR_STOP=1`).

No host:

```bash
make backup-db
make restore-db FILE=backups/cotag-dev-cotag-20261125-120000.sql.gz
make restore-db FILE=... ARGS=--confirm-prod   # somente prod
```

Dentro do container (quando o host nao tem cliente):

```bash
docker compose exec -T app /var/www/html/scripts/backup-db.sh
docker compose exec -T app /var/www/html/scripts/restore-db.sh /var/www/html/storage/backups/arquivo.sql.gz
```

No Portainer, prefira `BACKUP_DIR=/var/www/html/storage/backups` para que o
arquivo fique no volume persistente. No host, o padrao e `backups/`.

### 11.2 Nome e retencao

Padrao: `<projeto>-<env>-<db>-YYYYmmdd-HHMMSS.sql.gz`.

- Checksum: `<arquivo>.sha256`.
- Retencao: `BACKUP_RETENTION_DAYS` (padrao `7`; `0` desabilita).
- O restore valida o checksum quando o arquivo `.sha256` existe.
- O restore **nao** apaga o backup.

### 11.3 RPO/RTO e backup central

O backup do projeto **complementa**, e nao substitui, o backup central da
infraestrutura. A politica central (frequencia, retencao, criptografia, offsite)
e responsabilidade da infra e **nao foi identificada** neste repositorio.

Enquanto a infra nao definir os valores oficiais, adote como meta operacional:

- **RPO:** ate 24 horas (backup diario do projeto + backup central).
- **RTO:** ate 4 horas para restore em ambiente de contingencia.

### 11.4 Teste de restore

Faca um teste periodico (sugestao: trimestral) em banco descartavel, nunca em
producao:

1. Gere um backup com `make backup-db`.
2. Solicite a infra um banco/uso descartavel do mesmo ambiente.
3. Rode `restore-db.sh` apontando `DB_*` para o banco descartavel.
4. Rode `php artisan migrate --force` e valide contagens/consultas basicas.
5. Registre data, arquivo, checksum, tempo de restore e responsavel.
6. Descarte o banco de teste.

---

## 12. Branches

Regra unica, sem documento de mapeamento:

- a branch atual (`main`) e a branch alvo da infraestrutura;
- `main`/`master` e a referencia estavel;
- as demais branches sao temporarias;
- nao existe `docs/deploy/BRANCHES.md` nem documento vivo de mapeamento.

A producao atual esta em `main`, portanto nao ha cutover de branch. Se no futuro
a producao estiver temporariamente em outra branch/infra, ela **nao** deve ser
alterada por este contrato; registre a excecao no relatorio da execucao e
planeje o cutover separadamente.

---

## 13. Cutover e rollback (stack legada -> stack canonica)

Nao execute nada em producao sem autorizacao e janela aprovada.

### 13.1 Preparacao

1. Garanta que o banco central de producao esta disponivel e migrado.
2. Gere backup do banco e do storage antigo.
3. Recupere o `APP_KEY` atual (sem imprimir em logs publicos):
   ```bash
   docker exec cotag-app cat /var/www/html/storage/app/app_key
   ```
   Configure o valor em `APP_KEY` na nova stack.
4. Se houver midia em `storage/app/public`, copie para o novo volume nomeado
   antes do cutover.

### 13.2 Deploy da stack nova

1. Crie a stack `cotag-prod` no Portainer com `docker-compose.yml`.
2. Configure todas as variaveis com `APP_ENV=prod`, `DB_HOST=db.<dominio>`,
   `APP_DEBUG=false`, `RUN_SEEDERS=false`, `WARM_CACHE=true`.
3. Deploy. Nao remova a stack antiga ainda.
4. Valide de dentro do NPM:
   ```bash
   docker exec <container-do-npm> getent hosts cotag-prod-web
   docker exec <container-do-npm> curl -fsS http://cotag-prod-web:8080/up
   ```

### 13.3 Virada do NPM

1. Edite o Proxy Host de producao.
2. Troque Forward Hostname de `cotag-app` para `cotag-prod-web`.
3. Troque Forward Port de `80` para `8080`.
4. Salve e faca smoke test (`/`, `/admin`, `/up`).
5. Monitore logs de app/worker e fila.

### 13.4 Rollback

- Reverta o NPM para `cotag-app:80` e mantenha a stack antiga ligada.
- Nao apague volumes, backups ou dados.
- Se a stack nova tiver rodado migrations incompativeis, avalie restore do
  backup com a infra antes de qualquer acao destrutiva.
- O rollback de NPM e imediato; o rollback de dados exige janela e aprovacao.

---

## 14. Troubleshooting

### 502 Bad Gateway no NPM

- Confirme que o app esta na rede `web` e com alias `cotag-<env>-web`.
- Confirme a porta `8080` no NPM.
- Verifique `docker compose ps` e `make logs`.
- Teste `docker exec <npm> getent hosts cotag-<env>-web`.

### Erro de DNS/banco

```bash
docker compose exec app getent hosts "$DB_HOST"
docker compose exec app php artisan db:show
docker compose exec app /var/www/html/scripts/wait-for-db.sh
```

- `DB_HOST` deve ser dominio, nao servico Compose.
- Verifique firewall, usuario, senha e permissoes.
- Nao use `extra_hosts` em producao.

### Migrations falham

- Verifique `DB_*`, permissoes DDL e conectividade.
- Rode `make migrate` e veja `make logs`.
- Em prod, migrations rodam no boot do app; o worker nao roda migrations.

### Seeders bloqueados

`RUN_SEEDERS=true` com `APP_ENV=prod` faz o container falhar de proposito.
Use `false` em producao e crie o admin via Senha Unica.

### APP_KEY mudou / sessoes invalidas

- Defina `APP_KEY` fixo no Portainer.
- Nao deixe o entrypoint gerar chave em producao sem persistencia.
- Se o volume foi recriado, restaure a chave ou aceite invalidar sessoes.

### Permissao no storage

O entrypoint roda como root para criar/ajustar permissoes do volume. O
supervisord (PID 1) e os masters de Nginx/PHP-FPM rodam como root para abrir
os pipes de log e a porta interna; os workers de Nginx/PHP-FPM e o queue worker
rodam como `www-data`. Se houver erro de permissao:

```bash
docker compose exec -u root app chown -R www-data:www-data storage bootstrap/cache
docker compose exec -u root app chmod -R 775 storage bootstrap/cache
```

### Worker nao processa

```bash
docker compose logs -f worker
docker compose exec app php artisan queue:monitor
```

Verifique `QUEUE_CONNECTION`, supervisor e conectividade com o banco.

### 413 / upload grande

- Ajuste `client_max_body_size` no NPM e no Nginx interno (padrao 100M).
- Verifique `post_max_size`/`upload_max_filesize` no `php.ini`.

### Mixed content / URL http

- `APP_URL` deve ser `https://...`.
- O NPM deve enviar `X-Forwarded-Proto`.
- `bootstrap/app.php` ja confia nos proxies.
- Limpe caches: `make shell` e `php artisan view:clear`.

### Cliente de backup ausente

- Rode o script dentro do container da aplicacao (a imagem inclui
  `default-mysql-client` e `postgresql-client`), ou instale o cliente no host.

---

## 15. Seguranca

- [ ] `APP_DEBUG=false` em homolog/prod.
- [ ] `RUN_SEEDERS=false` em prod.
- [ ] `APP_KEY` forte e exclusivo por ambiente, no Portainer.
- [ ] Senhas de banco longas e exclusivas do projeto.
- [ ] Banco nao exposto publicamente e fora da rede `web`.
- [ ] Aplicacao sem portas publicadas.
- [ ] HTTPS forcado no NPM, HSTS e cookies seguros.
- [ ] Nenhum `.env` no servidor; variaveis no Portainer.
- [ ] Nenhum segredo em compose, Dockerfile, README ou Git.
- [ ] Backup central da infra ativo; backup do projeto testado.
- [ ] Logs sem PII/segredos/connection strings completas.
- [ ] Rotacao de segredos documentada para o futuro secret manager.

---

## 16. O que fica manual

- Criacao das stacks no Portainer e preenchimento das variaveis.
- Criacao/atualizacao dos Proxy Hosts e TLS no NPM.
- DNS publico e DNS interno do banco.
- Criacao de banco/usuario/permissoes na infra.
- Entrega de segredos por canal seguro.
- Backup central, monitoramento e alertas.
- Janela e aprovacao do cutover de producao.
- Eventual migracao de midia do storage antigo para o volume novo.

---

## 17. Nao identificado / limitacoes

- Dominios reais, host do NPM, DNS do banco e nomes das stacks da infra.
- Politica central de backup (frequencia, retencao, criptografia, offsite).
- RPO/RTO oficiais da infraestrutura.
- Se a producao legada usa exatamente `cotag-app:80` e bind mount `./storage`
  (isso foi inferido da documentacao antiga e dos arquivos legados).
- O cutover de producao nao foi executado; a stack antiga nao foi alterada.
- A validacao de build/teste depende de Docker, rede e do banco externo de dev.
- Versoes dos servidores MySQL/MariaDB/PostgreSQL de cada ambiente nao foram
  identificadas. O cliente PostgreSQL da imagem e 17 e precisa ser compativel
  com o servidor do ambiente.
- O PID 1 (supervisord) e os masters de Nginx/PHP-FPM rodam como root para
  abrir os pipes de log e a porta interna; os processos de aplicacao rodam como
  `www-data`. Para uma politica de PID 1 totalmente nao-root, avaliar uma
  imagem unprivileged/porta alta em trabalho separado.

---

## 18. Arquivos legados

Os arquivos antigos estao em `docs/deploy/_legacy/` com uma nota. Eles nao
fazem parte do contrato e nao devem ser usados em novas stacks. Nenhum banco ou
dado foi removido.

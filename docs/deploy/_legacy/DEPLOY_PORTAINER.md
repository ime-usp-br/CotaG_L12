# Deploy em Produção - Portainer + Nginx Proxy Manager (NPM)

**Versão:** 1.0.0
**Data:** 2026-11-24

Este guia descreve o deploy do **CotaG** no mesmo modelo usado pelo
**Nova Europa**:

- **app**: Nginx + PHP-FPM no mesmo container (`cotag-app`), sem portas publicadas no host
- **worker**: processamento de filas (`cotag-worker`)
- **banco de dados**: externo ao stack (MySQL/MariaDB existente)
- **rede**: externa `web`, compartilhada com o NPM e o Portainer
- **HTTPS**: terminado no **Nginx Proxy Manager**, que encaminha para `cotag-app:80`

---

## 1. Arquitetura

```
Internet
   │
   ▼
Nginx Proxy Manager (80/443)  ── rede "web"
   │  proxy_pass http://cotag-app:80
   ▼
cotag-app  (Nginx + PHP-FPM)  ── rede "web" + default
   │
   ├── MySQL/MariaDB externo (<host-do-banco>:<porta-do-banco> ou outro host)
   └── Replicado USP (<host-do-replicado>:1433)

cotag-worker (queue worker)   ── rede "web" + default
```

Arquivos envolvidos:

| Arquivo | Uso |
|---------|-----|
| `docker-compose.yml` | Desenvolvimento local (Laravel Sail). Não usar no Portainer. |
| `docker-compose.prod.yml` | **Stack do Portainer** (produção). |
| `docker-compose.prod.local.yml` | Opcional: testar a imagem de produção localmente. |
| `docker/production/Dockerfile` | Imagem de produção. |
| `docker/production/entrypoint.sh` | Inicialização (APP_KEY, banco, migrations, caches). |
| `.dockerignore` | Reduz o contexto de build e não copia `.env`. |

---

## 2. Pré-requisitos

- Portainer CE em execução
- Nginx Proxy Manager em execução na **mesma rede `web`**
- rede `web` criada:

```bash
docker network create web
```

- Banco MySQL/MariaDB acessível pelo container (host + porta + usuário + base)
- Acesso de saída do servidor para `<host-do-replicado>:1433`
- Repositório do CotaG disponível no GitHub (`ime-usp-br/CotaG_L12`) ou o
  `docker-compose.prod.yml` colado no editor do Portainer

Verificações rápidas (substitua os nomes em MAIÚSCULAS pelos valores do seu ambiente):

```bash
docker network inspect web >/dev/null && echo "rede web OK"
docker ps --format '{{.Names}}' | grep -Ei 'proxy|portainer'
getent hosts HOST_DO_REPLICADO
```

---

## 3. Preparar o banco de dados

O stack **não** sobe MySQL: use o servidor de banco já existente no ambiente
(host, porta, usuário e base definidos pela infraestrutura).

Crie a base e o usuário (exemplo para MariaDB/MySQL):

```sql
CREATE DATABASE IF NOT EXISTS cotag
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'cotag_user'@'%' IDENTIFIED BY 'TROQUE_ESTA_SENHA';
GRANT ALL PRIVILEGES ON cotag.* TO 'cotag_user'@'%';
FLUSH PRIVILEGES;
```

Teste a conexão a partir de um container qualquer na rede `web`:

```bash
docker run --rm --network web mysql:8.0 \
  mysql -h HOST_DO_BANCO -P PORTA_DO_BANCO -u cotag_user -p -e "SELECT 1" cotag
```

> Em produção real, ajuste `DB_HOST`/`DB_PORT` para o servidor de banco
> homologado. Não use o banco de desenvolvimento em produção.
>
> Se o banco estiver na mesma rede Docker, use o nome do serviço/container como
> `DB_HOST` e a porta interna (ex.: `DB_HOST=banco` e `DB_PORT=3306`).

---

## 4. Variáveis de ambiente do stack

No Portainer: **Stacks → Add stack → Environment variables**.

### Obrigatórias

| Variável | Exemplo | Observação |
|----------|---------|------------|
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | nunca `true` em produção |
| `APP_URL` | `https://SEU_DOMINIO` | deve ser a URL pública com HTTPS |
| `APP_KEY` | `base64:...` | gere uma vez e guarde; ver abaixo |
| `DB_CONNECTION` | `mysql` | |
| `DB_HOST` | `<host-do-banco>` | host do banco |
| `DB_PORT` | `<porta-do-banco>` | porta do banco |
| `DB_DATABASE` | `cotag` | |
| `DB_USERNAME` | `cotag_user` | |
| `DB_PASSWORD` | `********` | |
| `SENHAUNICA_KEY` | `SEU_CLIENT_ID` | credencial OAuth |
| `SENHAUNICA_SECRET` | `********` | |
| `SENHAUNICA_CALLBACK_ID` | `<callback_id>` | |
| `REPLICADO_HOST` | `<host-do-replicado>` | |
| `REPLICADO_PORT` | `1433` | |
| `REPLICADO_DATABASE` | `replicado` | |
| `REPLICADO_USERNAME` | `USUARIO_REPLICADO` | |
| `REPLICADO_PASSWORD` | `********` | |
| `REPLICADO_CODUNDCLG` | `<codundclg>` | código da unidade |

### Opcionais (já têm defaults no compose)

| Variável | Default | Observação |
|----------|---------|------------|
| `APP_NAME` | `CotaG` | |
| `APP_VERSION` | `latest` | tag da imagem local |
| `APP_LOCALE` | `pt_BR` | |
| `LOG_STACK` | `daily` | |
| `LOG_LEVEL` | `warning` | |
| `SESSION_DRIVER` | `database` | |
| `CACHE_STORE` | `database` | |
| `QUEUE_CONNECTION` | `database` | |
| `REPLICADO_SYBASE` | `true` | encoding legado do Replicado |
| `RUN_MIGRATIONS` | `true` | migrations automáticas no start |
| `RUN_SEEDERS` | `false` | **não** ligue em produção (ver seção 7) |
| `MAIL_*` | — | configure SMTP se necessário |

### Gerar o `APP_KEY`

Opção A (recomendada) - gerar localmente com a imagem já construída:

```bash
docker run --rm cotag:latest php artisan key:generate --show
```

Copie a saída (`base64:...`) para a variável `APP_KEY` do stack.

Opção B - deixar o entrypoint gerar e persistir:

Se `APP_KEY` estiver vazia, o container gera uma chave e grava em
`storage/app/app_key`. **Atenção:** se o stack for recriado a partir do Git e o
diretório `./storage` for perdido, a chave muda e as sessões/dados
criptografados deixam de funcionar. Por isso, prefira a opção A.

---

## 5. Criar o stack no Portainer

### Opção A - Repositório Git (recomendado)

1. **Stacks → Add stack**
2. Nome: `cotag`
3. Build method: **Repository**
4. Repository URL: `https://github.com/ime-usp-br/CotaG_L12`
5. Repository reference: `refs/heads/main`
6. **Compose path:** `docker-compose.prod.yml`
7. Preencha as variáveis de ambiente da seção 4
8. **Deploy the stack**

O Portainer lê apenas o `docker-compose.prod.yml`; o
`docker-compose.prod.local.yml` é ignorado (é só para testes locais).

### Opção B - Web editor

1. **Stacks → Add stack**
2. Cole o conteúdo de `docker-compose.prod.yml`
3. Preencha as variáveis de ambiente
4. **Deploy the stack**

### Verificar

```bash
docker ps --filter name=cotag
docker logs -f cotag-app
```

O log deve terminar com algo como:

```
✅ Database is ready!
🔧 Running migrations...
⚡ Optimizing application...
✨ Container ready! Starting services...
```

Teste a saúde de dentro do host:

```bash
docker exec cotag-app curl -fsS http://localhost/up
```

---

## 6. Configurar o Nginx Proxy Manager

No painel do NPM (**http://HOST_DO_NPM:81**):

1. **Hosts → Proxy Hosts → Add Proxy Host**
2. **Details**
   - Domain Names: `SEU_DOMINIO` (ou o domínio de produção)
   - Scheme: `http`
   - Forward Hostname / IP: `cotag-app`  ← **nome do container**, não `localhost`
   - Forward Port: `80`
   - **Websockets Support: ON** (obrigatório para Livewire/Filament)
   - Block Common Exploits: ON
3. **SSL**
   - Request a new SSL certificate (Let's Encrypt) ou use o certificado wildcard/custom da sua organização
   - Force SSL: ON
   - HTTP/2: opcional
4. Salvar

O NPM e o `cotag-app` precisam estar na mesma rede Docker (`web`). Se aparecer
**502 Bad Gateway**:

```bash
docker network inspect web | grep cotag-app
docker exec CONTAINER_DO_NPM getent hosts cotag-app
```

---

## 7. Primeiro acesso e seeders

As migrations são executadas automaticamente pelo entrypoint
(`RUN_MIGRATIONS=true`). Os seeders **não** rodam por padrão para evitar a
criação do usuário inseguro `admin@usp.br / password`.

Execute os seeders necessários uma única vez:

```bash
# Papéis/permissões e regras de cotas (idempotentes)
docker exec -it cotag-app php artisan db:seed --class=RoleSeeder --force
docker exec -it cotag-app php artisan db:seed --class=CotaSeeder --force
```

Crie o primeiro administrador:

```bash
docker exec -it cotag-app php artisan make:filament-user
# ou, para usuários USP, faça login via Senha Única e atribua a role Admin:
docker exec -it cotag-app php artisan tinker
```

```php
$user = \App\Models\User::where('email', 'seu-email@usp.br')->first();
$user->assignRole('Admin');
```

> **Nunca** rode `DatabaseSeeder`/`FilamentAdminSeeder` em produção sem trocar
> a senha padrão. O ideal é usar Senha Única e conceder a role `Admin`.

Acesse:

- Aplicação: `https://SEU_DOMINIO`
- Painel Filament: `https://SEU_DOMINIO/admin`

---

## 8. Atualizações (deploy de nova versão)

### Via Portainer (Git)

1. **Stacks → cotag → Pull and redeploy** (ou **Update the stack**)
2. Marque **Re-pull image** / **Re-build image** para reconstruir a imagem
3. Confirme

As migrations novas rodam automaticamente no start do `cotag-app`.

### Via CLI (na máquina, sem Portainer)

```bash
cd /caminho/CotaG_L12
git pull
docker compose -f docker-compose.prod.yml build --pull
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml logs -f app
```

Para aplicar apenas migrations/caches sem recriar:

```bash
docker exec cotag-app php artisan migrate --force
docker exec cotag-app php artisan view:clear
docker exec cotag-app php artisan view:cache
docker exec cotag-app php artisan filament:optimize
```

> `route:cache` não é usado porque `routes/web.php` possui rotas com closures.

---

## 9. Backup e restore

### Banco de dados

A imagem da aplicação não inclui o cliente `mysqldump`. Use um container
MySQL/MariaDB na rede `web`:

```bash
docker run --rm --network web -e MYSQL_PWD='SUA_SENHA' mysql:8.0 \
  mysqldump -h HOST_DO_BANCO -P PORTA_DO_BANCO -u cotag_user cotag \
  > cotag-$(date +%Y%m%d-%H%M%S).sql
```

> Evite deixar a senha no histórico do shell; em produção use um arquivo de
> credenciais ou o próprio Portainer para agendar o dump.

### Storage

O stack usa bind mount de `./storage` (no Portainer, o diretório do stack, algo
como `/data/compose/ID_DO_STACK/storage`). Faça o backup no host:

```bash
tar czf cotag-storage-$(date +%Y%m%d).tar.gz -C /data/compose/ID_DO_STACK/storage .
```

### Restore

```bash
mysql -h HOST_DO_BANCO -P PORTA_DO_BANCO -u cotag_user -p cotag < backup.sql
tar xzf cotag-storage-YYYYMMDD.tar.gz -C /data/compose/ID_DO_STACK/storage
```

---

## 10. Troubleshooting

### 502 Bad Gateway no NPM

- `cotag-app` não está na rede `web`
- container reiniciando (veja `docker logs cotag-app`)
- nome de forward errado (deve ser `cotag-app`, não `localhost`)

### Mixed content / assets em `http://`

- `APP_URL` deve ser `https://SEU_DOMINIO`
- o NPM deve enviar `X-Forwarded-Proto` (o proxy padrão envia)
- `bootstrap/app.php` já confia nos proxies (`trustProxies`)
- limpe caches: `php artisan view:clear` e recarregue

### Erro de banco (`SQLSTATE[HY000] [2002]`)

```bash
docker exec cotag-app php artisan db:show
docker exec cotag-app php -r 'var_dump(getenv("DB_HOST"), getenv("DB_PORT"));'
```

Verifique `DB_*`, firewall e se o usuário tem permissão na base.

### Replicado indisponível

```bash
docker exec cotag-app php -r 'var_dump(getenv("REPLICADO_HOST"), getenv("REPLICADO_PORT"));'
docker exec cotag-app php artisan tinker
>>> DB::connection('replicado')->getPdo();
```

- O host/porta deve ser alcançável de dentro do container.
- O `docker/production/entrypoint.sh` gera `/etc/freetds/freetds.conf` com
  `REPLICADO_HOST`/`REPLICADO_PORT`.
- O pacote `uspdev/replicado` usa `pdo_dblib`; as consultas próprias do CotaG
  usam `pdo_sqlsrv`. Ambas as extensões estão na imagem.
- Em produção, use o host/porta do Replicado fornecidos pela infraestrutura.

### Filament sem CSS/JS (404 em `/css/filament/...`)

```bash
docker exec cotag-app php artisan filament:assets
docker exec cotag-app php artisan filament:optimize
```

### Erro de permissão no storage

```bash
docker exec cotag-app chown -R www-data:www-data storage bootstrap/cache
docker exec cotag-app chmod -R 775 storage bootstrap/cache
```

### Container em loop de restart

```bash
docker logs --tail=100 cotag-app
```

Causas comuns: `APP_KEY` inválida, banco inacessível, migration com erro,
`DB_*` incorreto.

---

## 11. Segurança

- [ ] `APP_DEBUG=false` e `APP_ENV=production`
- [ ] `APP_KEY` forte, definida no stack e guardada em local seguro
- [ ] senhas de banco longas e exclusivas para o CotaG
- [ ] banco **não** exposto publicamente
- [ ] HTTPS forçado no NPM
- [ ] `cotag-app` sem portas publicadas no host
- [ ] backup diário do banco e do `storage`
- [ ] remover/alterar o usuário padrão de seeders
- [ ] revisar permissões de roles (`Admin`, `Operador`)

---

## 12. Teste local da imagem de produção

Útil antes de subir para o Portainer:

```bash
docker network create web   # se ainda não existir

docker compose -f docker-compose.prod.yml -f docker-compose.prod.local.yml up -d --build
docker compose -f docker-compose.prod.yml -f docker-compose.prod.local.yml logs -f app
```

Acesse `http://localhost:8000`. Para derrubar:

```bash
docker compose -f docker-compose.prod.yml -f docker-compose.prod.local.yml down
# apagar o banco local de teste:
docker compose -f docker-compose.prod.yml -f docker-compose.prod.local.yml down -v
```

> Se já existir um `.env` de desenvolvimento na raiz, o Compose usa esses
> valores na interpolação. Para simular produção, crie um arquivo separado
> (por exemplo `.env.prod.local`, sem versionar) e rode:
>
> ```bash
> docker compose --env-file .env.prod.local \
>   -f docker-compose.prod.yml -f docker-compose.prod.local.yml up -d --build
> ```
>
> Se a porta `8000` estiver ocupada, defina `APP_PORT=8017` (ou outra) no
> arquivo de ambiente. Para o teste, use `DB_HOST=mysql` (o MySQL do override)
> e `APP_ENV=production`. Defina também `WWWUSER=$(id -u)` e
> `WWWGROUP=$(id -g)` para que o diretório `./storage` continue gravável pelo
> seu usuário no host.

O desenvolvimento do dia a dia continua com o Laravel Sail:

```bash
./vendor/bin/sail up -d
```

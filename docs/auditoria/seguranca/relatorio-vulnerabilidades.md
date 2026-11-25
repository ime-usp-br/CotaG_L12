# Relatório Integral de Vulnerabilidades e Riscos — CotaG

**Versão do relatório:** 1.0  
**Data:** 2026-11-25  
**Objeto:** Sistema CotaG — gestão de cotas de impressão e cópias do IME-USP  
**Base analisada:** árvore de trabalho do repositório `CotaG_L12`, commit base `c007fef`, incluindo arquivos não versionados presentes no diretório (`.env`, `storage/`)  
**Método:** revisão estática, somente leitura, de código, rotas, configurações, migrations, seeders, testes, documentação, dependências, containers e CI; nenhuma execução de exploit, migration, seeder, teste dinâmico ou acesso a sistemas externos  
**Status:** final, com limitações explícitas; conclusões que dependem de ambiente, dados reais ou teste dinâmico estão marcadas como “necessita verificação”

> Todos os segredos, chaves, credenciais e dados pessoais identificados neste relatório estão redigidos. O relatório informa localização, tipo e impacto, nunca o valor.

---

## 1. Sumário executivo

O CotaG é um sistema institucional que controla cotas mensais de impressão/cópias, registra débitos e créditos e mantém histórico de consumo por pessoa. A implementação atual usa Laravel 12, FilamentPHP 4, Livewire/Volt, MySQL/MariaDB, autenticação dupla (Senha Única USP e login local) e integração com o Replicado da USP.

A postura geral de risco é **alta a crítica**. O sistema tem controles básicos corretos — CSRF do framework, hash de senha, verificação de e-mail, rate limit no login local, políticas de recurso no Filament, auditoria de alguns modelos e separação de containers — mas esses controles não compensam quatro problemas estruturais:

1. **Caminhos de tomada de conta administrativa.** Há credenciais padrão em seeders, um seeder que pode promover uma conta pré-existente e um canal paralelo de administração/impersonação criado pelo pacote Senha Única (`/loginas`, `/senhaunica-users`), fora do RBAC e da auditoria da aplicação.
2. **Destruição de histórico financeiro e de auditoria.** A exclusão de usuário, operador ou pessoa remove lançamentos por `ON DELETE CASCADE`. Um administrador ou o próprio operador pode apagar seu histórico de transações.
3. **Regras de negócio sem fechamento, versionamento ou controle de crédito.** O saldo é sempre recalculado com as cotas e vínculos atuais; não há fechamento mensal, snapshot, trilha imutável nem limite/aprovação para créditos. O passado pode mudar e créditos expiram na virada do mês.
4. **Dados pessoais e credenciais expostos por configuração.** O debug OAuth do pacote Senha Única grava nome, e-mails, telefone e vínculos por padrão; o tráfego do Replicado via `pdo_dblib` está sem criptografia; cookies de sessão não têm `Secure`; e o `MAIL_MAILER=log` pode gravar tokens de redefinição em log.

**Top 10 riscos**

| # | Risco | Por que importa |
|---|---|---|
| 1 | Credenciais administrativas padrão e seeder que promove conta pré-existente | Tomada de conta administrativa completa e persistente |
| 2 | Exclusão em cascata de lançamentos ao remover usuário/pessoa | Perda de histórico financeiro, auditoria e capacidade de reconciliação |
| 3 | Canal paralelo `/loginas` e `/senhaunica-users` | Impersonação e alteração de papéis fora do RBAC e sem auditoria |
| 4 | Ausência de fechamento mensal/versionamento | O passado muda quando cotas ou vínculos são editados; relatórios não são reproduzíveis |
| 5 | Vínculos locais obsoletos e falha parcial do Replicado | Cota errada, inclusive cota zero silenciosa, com decisão de débito/crédito sobre dado falso |
| 6 | Crédito arbitrário por operador, sem limite, motivo ou aprovação | Fraude interna, concessão indevida e perda financeira |
| 7 | Auditoria incompleta e mutável | Concessão/revogação de papéis, impersonação, imports e exclusões não são rastreáveis |
| 8 | Debug OAuth com PII e ausência de retenção | Exposição de dados pessoais em storage, backup, host e imagem |
| 9 | Replicado sem criptografia e com validação de certificado desligada | Interceptação de credenciais e dados pessoais na rede |
| 10 | Testes e CI sem confiança, dependências EOL e supply chain não verificado | Regressões de segurança passam; atualizações e artefatos não são confiáveis |

**O que já está adequado e deve ser preservado**

- Laravel 12/Filament 4 atualizados no lockfile, com CSRF, escaping Blade, Eloquent com bindings e hash de senha.
- Login local com rate limit de 5 tentativas por e-mail+IP, mensagem genérica de falha, regeneração de sessão e logout com invalidação.
- Verificação de e-mail com URLs assinadas e `throttle:6,1`; política de senha forte para autoatendimento.
- `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, cookie `HttpOnly`, `SameSite=Lax`, bloqueio de dotfiles no Nginx e container de produção sem portas publicadas no host.
- `APP_DEBUG=false`, `RUN_SEEDERS=false` e `APP_ENV=production` como defaults do compose de produção; `.dockerignore` exclui `.env` e artefatos locais.
- Auditoria de `User`, `Cota`, `CotaEspecial`, `Lancamento`, `Role` e `Permission`; `Extrato` e `Audit` somente leitura na UI; políticas de recurso no Filament.
- Testes e fakes para Replicado/Senha Única, lockfiles de Composer e npm, Pint e Larastan no CI.

**Ação imediata (P0, até 7 dias)**

- Remover ou tornar inócuos os seeders de administrador/usuário de teste; nunca executar `DatabaseSeeder`/`FilamentAdminSeeder` em produção; rotacionar qualquer credencial que possa ter sido exposta.
- Alterar as chaves estrangeiras de `lancamentos` para impedir exclusão em cascata e substituir exclusão física por desativação/anonymização com retenção.
- Desabilitar `/loginas` e `/senhaunica-users` ou colocá-los sob o RBAC da aplicação, com auditoria de impersonação e de alterações de papel.
- Definir e implementar fechamento mensal/snapshot imutável e política de crédito (limite, motivo, aprovação e origem).
- Desligar `SENHAUNICA_DEBUG`, purgar os arquivos já gerados, removê-los do contexto de build/imagem e definir retenção de PII (F-04, F-25).
- Exigir TLS com validação de certificado no Replicado e revisar o `trustProxies`/cookies seguros.

**O que exige decisão do produto**

Quem pode se registrar; se login local é permitido para administradores/operadores; se `/loginas` deve existir; qual é a tabela oficial de cotas e quem a aprova; se créditos expiram; se saldo negativo é dívida; qual é a base legal e a retenção de dados; qual é o RTO/RPO; se dependências GPL são aceitáveis no modelo de distribuição.

**O que exige verificação**

Teste dinâmico de autorização e impersonação; restore real de backup; comportamento do Replicado em falha parcial; existência de MFA na Senha Única; configuração efetiva do NPM/TLS/HSTS; dados reais e volumes; contratos e bases legais; CVEs das versões em produção; e se os seeders já foram executados em algum ambiente.

---

## 2. Escopo, método, fontes e limitações

### 2.1 Escopo analisado

- Código de aplicação em `app/`, rotas em `routes/`, views em `resources/views/`, configurações em `config/`, migrations, seeders, factories, testes, documentação em `docs/`, README, AGENTE.md, CHANGELOG, workflows de CI, Dockerfiles, compose de desenvolvimento/produção, `.dockerignore`, `.env.example`, arquivos de ambiente versionados e dependências declaradas/lockadas.
- Comportamento relevante de pacotes de terceiros efetivamente usados: `uspdev/senhaunica-socialite`, `uspdev/replicado`, `spatie/laravel-permission`, `owen-it/laravel-auditing`, `filament/filament`, `livewire/livewire`.
- Artefatos locais não versionados foram inspecionados apenas para classificação de exposição: `.env`, `storage/app/private/debug/oauth`, `storage/logs/laravel.log`. Valores não são reproduzidos.

### 2.2 Método

1. Reconhecimento de propósito, atores, ativos, fluxos, entradas, dados, acessos, operação, terceiros e testes.
2. Modelagem de ameaças com STRIDE e LINDDUN, casos de abuso e casos de falha.
3. Revisão técnica de autenticação, autorização, sessão, validação, criptografia, configuração, dependências, containers, CI/CD, logs e exposição.
4. Revisão de lógica de negócio: cotas, saldo, estados, temporalidade, créditos, aprovações, invariantes e concorrência.
5. Revisão de dados/privacidade, acesso/segregação, operação/resiliência, supply chain, documentação/testes e riscos organizacionais.
6. Classificação de severidade técnica (CVSS quando aplicável), risco de negócio, probabilidade, confiança e prioridade.
7. Proposição de remediação, mitigação, verificação e roadmap, sem implementar correções.

### 2.3 Limitações

- **Não houve teste dinâmico, DAST, pentest, exploração, brute force ou acesso a produção.** Achados que dependem de comportamento em runtime estão marcados como “necessita verificação”.
- **Não houve acesso ao banco real, ao Replicado, à Senha Única, ao NPM, ao Portainer, aos logs de produção, aos backups ou às listas de acesso.** A configuração efetiva de produção pode divergir do repositório.
- **Não houve execução da suíte de testes, migrations ou seeders.** As conclusões sobre testes são estáticas; a suíte pode falhar ou passar de forma diferente no ambiente real.
- **Não houve SCA/`composer audit`/`npm audit`, secret scanning, container scanning, IaC scanning ou consulta a bases de CVE.** As versões foram inventariadas, mas a existência de CVEs específicas não foi confirmada.
- **Não houve revisão jurídica formal.** Conclusões sobre LGPD, contratos, licenças GPL e obrigações institucionais exigem validação jurídica.
- **O histórico Git não foi integralmente auditado em busca de segredos.** Foram verificados arquivos rastreados e o estado atual; segredos removidos no passado podem permanecer no histórico.
- **A documentação do projeto foi tratada como evidência, não como autoridade.** Há contradições relevantes registradas neste relatório.
- **Não foram auditados fornecedores externos** (USP, NPM, provedor de nuvem, serviços de e-mail/fontes) nem aspectos físicos, de engenharia social ou de disponibilidade sob ataque.

---

## 3. Contexto do sistema

### 3.1 Propósito declarado e propósito provável

O README declara um sistema institucional do IME-USP para controlar cotas de impressão/cópias, com cotas regulares por vínculo, cotas especiais, débitos, créditos, saldo mensal, integração com Replicado e autenticação por Senha Única. O código confirma esse propósito, mas também revela um sistema em transição de starter kit: há funcionalidades de autoatendimento (registro local, perfil, reset de senha), rotas administrativas do pacote Senha Única e documentação que descreve o sistema legado Java/JSF.

### 3.2 Atores

- **Pessoa beneficiária:** aluno, docente ou servidor; não tem login no CotaG atual; é representada na tabela `pessoas`.
- **Operador:** funcionário da gráfica; acessa `/lancamento`, busca pessoas, registra débito/crédito.
- **Administrador:** acessa `/admin`, gerencia usuários, papéis, permissões, cotas, cotas especiais, extrato e auditoria.
- **Usuário autenticado comum:** `usp_user` ou `external_user`; pode acessar perfil/dashboard, mas não `/lancamento` sem a permissão `operar-sistema`.
- **Administrador do pacote Senha Única:** usuário listado em `SENHAUNICA_ADMINS` ou com permissão hierárquica `admin` no guard `senhaunica`; pode usar `/loginas` e `/senhaunica-users`.
- **Integrações:** Replicado USP (SQL Server), Senha Única (OAuth1), banco MySQL/MariaDB, NPM (TLS), SMTP/log de e-mail, fontes externas.

### 3.3 Ativos

- Saldo e histórico de lançamentos (valor, tipo, data, pessoa, operador).
- Cotas regulares e especiais (valor, vínculo, pessoa).
- Identidade e vínculos de pessoas (NUSP, nome, e-mail, telefone, vínculo).
- Credenciais e sessões de operadores/administradores; credenciais de banco e Replicado; chaves OAuth.
- Trilhas de auditoria (quem alterou o quê, quando, de qual IP/user agent).
- Disponibilidade do serviço e reputação institucional.
- Capacidade de prestar contas e de reconciliar consumo financeiro.

### 3.4 Fronteiras de confiança

- Internet → NPM → container `cotag-app` (HTTP interno).
- Navegador → sessão/cookies → Laravel.
- Aplicação → banco MySQL/MariaDB externo.
- Aplicação → Replicado USP (rede institucional; `pdo_dblib` sem TLS e `sqlsrv` com certificado não validado).
- Aplicação → Senha Única (OAuth1; redirecionamentos e callback).
- Aplicação → armazenamento local/volume `storage` (sessões, logs, debug OAuth, chave APP_KEY).
- Aplicação → SMTP/log de e-mail (tokens de verificação/reset).
- Navegador → fontes externas `fonts.bunny.net` (terceiro).

### 3.5 Pontos de entrada e superfície de ataque

- Rotas web da aplicação: `/`, `/lancamento`, `/dashboard`, `/profile`, `/register`, `/login/local`, `/forgot-password`, `/reset-password/{token}`, `/verify-email`, `/verify-email/{id}/{hash}`, `/confirm-password`, `/up`.
- Rotas do Filament: `/admin` e recursos/páginas.
- Rotas do pacote Senha Única: `/login`, `/callback`, `/logout`, `/loginas` (GET/POST), `/undologinas`, `/senhaunica-users`, `/senhaunica-users/find`, `/senhaunica-users/{id}/jsonModalContent`, `/senhaunica-users/{id}/updatePermission`.
- Formulários Livewire/Volt: registro, login local, busca de pessoa, seleção, débito/crédito, perfil, reset.
- Formulários Filament: usuários, papéis, permissões, cotas, cotas especiais, auditoria.
- Integrações de saída: Replicado, OAuth, e-mail, fontes.
- Não há API pública, webhooks, upload de arquivos ou comandos artesanais customizados expostos.

---

## 4. Matriz de cobertura

| Área | Status | Evidências principais | Observações |
|---|---|---|---|
| Documentação e propósito | Analisada | `README.md`, `AGENTE.md`, `docs/**`, `CHANGELOG.md` | Contradições entre docs, código e testes |
| Rotas e superfície | Analisada | `routes/web.php`, `routes/auth.php`, rotas do Filament e do pacote Senha Única | Rotas ocultas do pacote são relevantes |
| Autenticação/sessão | Analisada | `LoginForm`, `SenhaunicaController`, `config/auth.php`, `config/session.php`, `bootstrap/app.php` | Sem MFA; cookie `Secure` ausente |
| Autorização/papéis | Analisada | Policies, `AdminPanelProvider`, seeders, `User::canAccessPanel`, guard `senhaunica` | Dois sistemas de autorização |
| Lógica de negócio | Analisada | `CotaService`, `ManageLancamentos`, models, migrations, docs | Sem fechamento/versionamento; crédito arbitrário |
| Dados e schema | Analisada (estrutura) | migrations, models, factories | Sem acesso a dados reais; constraints ausentes |
| Integração Replicado | Analisada (código/config) | `ReplicadoService`, `config/database.php`, FreeTDS, pacote | Sem teste dinâmico; TLS fraco |
| Integração Senha Única | Analisada (código/config) | pacote, rotas, trait, config | Impersonação e debug OAuth |
| Filament/UI | Analisada | Resources, forms, tables, policies, blades | `strictAuthorization` desligado; políticas incoerentes |
| Privacidade/consentimento | Parcial | schema de dados, logs, debug, docs | Sem base legal/aviso/retenção no repositório; revisão jurídica necessária |
| Observabilidade | Parcial | logs Laravel, healthcheck, CI | Sem APM/alertas/runbooks |
| Backup/restore/DR | Parcial | `docs/DEPLOY_PORTAINER.md` | Manual, sem teste/criptografia/automação |
| Containers/IaC | Analisada (estática) | Dockerfiles, compose, Nginx, PHP-FPM, entrypoint, `.dockerignore` | Sem scan; build não reprodutível; PII pode ir para a imagem (F-25); rede compartilhada (F-26) |
| CI/CD | Analisada (estática) | `.github/workflows/laravel.yml` | Sem SCA/secret/container/IaC scan; Dusk fora |
| Dependências/licenças | Analisada (versões) | `composer.lock`, `package-lock.json` | CVEs não verificadas; GPL |
| Testes/qualidade | Analisada (estática) | `tests/**`, `phpunit.xml`, `phpstan.neon` | Testes contraditórios; não executados |
| Acesso/organização/terceiros | Parcial | código, docs, compose | Listas de acesso, contratos e SLAs indisponíveis |
| Banco real/volumes | Não analisada | — | Sem acesso; impacto desconhecido |
| NPM/TLS/rede de produção | Não analisada | — | Configuração externa ao repositório |
| Pentest/DAST/exploração | Não realizada | — | Fora do escopo por regra de segurança |
| Multi-tenant/API/mobile | N/A | — | Não há multi-tenancy, API pública ou app móvel no repositório |

---

## 5. Sumário de risco

### 5.1 Contagem por risco de negócio

| Risco de negócio | Quantidade | IDs |
|---|---:|---|
| Crítico | 4 | F-01, F-02, F-03, F-06 |
| Alto | 17 | F-04, F-05, F-07, F-08, F-09, F-10, F-11, F-12, F-13, F-14, F-15, F-16, F-20, F-21, F-22, F-23, F-25 |
| Médio | 9 | F-17, F-18, F-19, F-24, F-26, S-01, S-02, S-03, S-04 |
| Baixo/Informativo | 6 | S-05, S-06, S-07, S-08, S-09, S-10 |

> A contagem é de risco de negócio, não de CVSS. Um achado tecnicamente moderado (por exemplo, exclusão em cascata) pode ser crítico para o negócio; um achado tecnicamente alto pode ter risco menor se não houver exposição.

### 5.2 Mapa de calor (probabilidade × impacto)

| Probabilidade | Impacto crítico | Impacto alto | Impacto médio | Impacto baixo |
|---|---|---|---|---|
| Alta | F-06, F-10, F-11, F-13 | F-08, F-09, F-12, F-16, F-25 | S-01, S-02 | S-07 |
| Média | F-01, F-02, F-03 | F-04, F-05, F-07, F-14, F-15, F-20, F-21, F-22, F-23, F-26 | F-17, F-18, F-19, F-24 | S-05, S-06 |
| Baixa | — | — | S-03, S-04 | S-08, S-09, S-10 |

### 5.3 Top riscos por categoria

| Categoria | Riscos principais |
|---|---|
| Autenticação/identidade | F-01, F-03, F-15, F-16, F-17 |
| Autorização/segregação | F-03, F-13, F-14 |
| Lógica de negócio/financeiro | F-06, F-07, F-08, F-09, F-10, F-12 |
| Dados/privacidade | F-04, F-23, F-25 |
| Infraestrutura/rede | F-05, F-16, F-19, F-26 |
| Operação/resiliência | F-21, F-22 |
| Supply chain/legal | F-20, F-25 |
| Documentação/testes | F-21, F-24 |

---

## 6. Controles existentes e pontos fortes

1. **Framework e bibliotecas atuais:** Laravel 12.33, Filament 4.1, Livewire 3.6 e lockfiles versionados; uso extensivo de Eloquent com bindings, reduzindo injeção SQL.
2. **CSRF e escaping:** middleware web do Laravel e componentes Blade/Livewire com escaping; não foi encontrado `{!! !!}` com dado de usuário nos fluxos analisados.
3. **Senhas:** hash via cast `hashed` e `Hash::make`; política forte (`min 8`, maiúsculas/minúsculas, números, símbolos) para registro e troca de senha de autoatendimento.
4. **Login local:** rate limit de 5 tentativas por e-mail+IP, mensagem genérica `auth.failed`, regeneração de sessão no login e invalidação no logout.
5. **Verificação de e-mail:** `MustVerifyEmail`, middleware `verified`, URL assinada e `throttle:6,1` na rota de verificação.
6. **Cookies e headers:** `HttpOnly`, `SameSite=Lax`, `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`.
7. **Filament:** autenticação e `canAccessPanel` restringem o painel a `ADM`/`Admin`; políticas de recurso e recursos somente leitura para auditoria/extrato.
8. **Auditoria de modelos críticos:** `User`, `Cota`, `CotaEspecial`, `Lancamento`, `Role` e `Permission` usam o pacote de auditoria; alterações de cotas e lançamentos geram registros.
9. **Produção:** compose sem portas publicadas; `APP_DEBUG=false`, `RUN_SEEDERS=false` e `APP_ENV=production` como defaults; `.dockerignore` exclui `.env` e logs; Nginx bloqueia dotfiles; PHP-FPM roda workers como `www-data`. **Ressalva:** `storage/app/private/` é re-incluído no contexto de build e pode levar PII para a imagem (F-25).
10. **CI:** Pint, Larastan e PHPUnit são executados; há fakes para Replicado/Senha Única e testes de feature/unitários.

Esses controles são reais, mas parciais. Por exemplo, a auditoria de modelos não cobre alterações de pivô de papéis; o rate limit do login não cobre registro; e a restrição do painel convive com um canal administrativo paralelo do pacote Senha Única.

---

## 7. Achados detalhados

> Convenções: **Severidade técnica** usa CVSS v3.1 quando aplicável; **Risco de negócio** usa Crítico/Alto/Médio/Baixo/Informativo; **Confiança** usa Confirmado/Provável/Necessita verificação/Informativo; **Prioridade** usa P0–P3. As linhas citadas correspondem ao snapshot analisado.

### F-01 — Credenciais administrativas padrão e seeder que pode promover conta pré-existente

- **Categoria:** Autenticação, configuração, operação.
- **Severidade técnica:** Crítica (CVSS 9.8, `AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H`), condicionada à execução de seeders ou exposição das credenciais padrão.
- **Risco de negócio:** Crítico.
- **Probabilidade:** Média (depende de `--seed`, `RUN_SEEDERS=true` ou execução manual; o README induz `--seed`).
- **Confiança:** Confirmado no código; exploração condicional.
- **Componentes/evidência:**
  - `database/seeders/FilamentAdminSeeder.php:29-35` cria o e-mail administrativo padrão (redigido) com senha padrão fraca e conhecida (valor no código, aqui redigido) e a imprime em `:43`.
  - `database/seeders/DatabaseSeeder.php:18-30` cria a conta de teste com o e-mail de teste padrão (redigido) e senha padrão fraca (valor redigido) e chama `FilamentAdminSeeder` e `CotaSeeder`.
  - `README.md:81-88` instrui `php artisan migrate --seed`.
  - `docker-compose.prod.yml:85` define `RUN_SEEDERS=false` por padrão; `docker/production/entrypoint.sh:110-112` executa seeders se a variável for `true`.
  - `docs/DEPLOY_PORTAINER.md:246-270` reconhece o risco e manda não rodar `DatabaseSeeder`/`FilamentAdminSeeder`, mas mantém os seeders no repositório e manda rodar `CotaSeeder`.
  - `FilamentAdminSeeder` usa `firstOrCreate` por e-mail (`:29`): se a conta já existir, a senha não é redefinida; em seguida, atribui a role `Admin` (`:39-40`). Uma conta pré-registrada com o e-mail administrativo padrão (redigido) seria promovida.
  - O registro público (`routes/auth.php:8-9`) permite criar conta com esse e-mail; o campo `sou_da_usp` é controlado pelo cliente (`resources/views/livewire/pages/auth/register.blade.php:66-91`), permitindo pular a validação de NUSP/e-mail ao defini-lo como falso.
  - O login do Filament não exige `email_verified_at`; `canAccessPanel` verifica apenas o papel (`vendor/filament/filament/src/Auth/Pages/Login.php:107-127`; `app/Models/User.php:80-88`). Assim, a conta pré-registrada e promovida entra no painel mesmo com e-mail não verificado.
- **Descrição:** Há duas fraquezas combináveis: credenciais padrão conhecidas e um seeder que promove uma conta existente em vez de criar uma conta nova com senha obrigatoriamente trocada. Em um deploy que rode `--seed` (documentado no README) ou que habilite `RUN_SEEDERS`, o sistema nasce com administrador conhecido. Se um atacante registrar o e-mail administrativo padrão (redigido) antes do seeder, o `firstOrCreate` encontra a conta do atacante e concede `Admin`.
- **Cenário de abuso:** Atacante registra o e-mail administrativo padrão (redigido), com `sou_da_usp=false` para evitar a validação do Replicado, aguarda ou induz a execução de `db:seed` e faz login com a senha que definiu; ou faz login direto com a credencial padrão após um deploy com seed.
- **Pré-condições:** seeders executados; ou credenciais padrão não alteradas; ou conta pré-registrada antes do seeder. Acesso à rede/aplicação.
- **Impacto técnico:** tomada de conta administrativa, criação/alteração de papéis, acesso a auditoria, cotas, lançamentos e dados pessoais.
- **Impacto de negócio:** fraude de cotas, alteração de histórico, vazamento de PII, perda de confiança institucional e possível obrigação de notificação.
- **Controles existentes:** `RUN_SEEDERS=false` por padrão no compose; aviso na documentação de deploy.
- **Controles ausentes:** seeders inseguros no código; ausência de bloqueio de credenciais padrão; ausência de verificação de que a conta criada é nova; `firstOrCreate` sem reset de senha/atributo de setup; README contraditório.
- **Remediação:** remover usuários padrão dos seeders de produção; separar seed de desenvolvimento de bootstrap de produção; criar administrador por comando interativo com senha definida no momento e troca obrigatória; fazer o bootstrap falhar se a conta já existir; exigir variável explícita e one-time token; remover o e-mail de teste padrão (redigido); documentar e testar o caminho seguro. Não manter o e-mail administrativo padrão (redigido) como identidade privilegiada.
- **Mitigação temporária:** garantir `RUN_SEEDERS=false`; verificar e remover contas padrão; forçar reset de senha; bloquear o e-mail administrativo padrão no registro; monitorar logins desse e-mail.
- **Esforço/dependências:** S/M; depende de decisão sobre bootstrap de administrador e do processo de deploy.
- **Prioridade:** P0.
- **Critérios de verificação:** em ambiente de teste, `RUN_SEEDERS=true` não cria conta com senha conhecida; tentativa de login com a credencial padrão falha; tentativa de pré-registrar o e-mail e depois rodar o seeder não concede `Admin`; o bootstrap exige senha/token e registra auditoria.
- **Testes sugeridos:** teste de integração do bootstrap; teste de regressão de seed; teste de login negativo; teste de registro com e-mail reservado.

### F-02 — Exclusão em cascata apaga lançamentos financeiros e trilha de responsabilidade

- **Categoria:** Integridade de dados, auditoria, lógica de negócio.
- **Severidade técnica:** Alta (CVSS 7.1 aproximado; `AV:N/AC:L/PR:H/UI:N/S:U/C:N/I:H/A:H`), com impacto de negócio crítico.
- **Risco de negócio:** Crítico.
- **Probabilidade:** Alta (basta um administrador excluir um usuário ou uma pessoa; o próprio usuário pode excluir a própria conta).
- **Confiança:** Confirmado.
- **Componentes/evidência:**
  - `database/migrations/2025_10_14_165401_create_lancamentos_table.php:20-28` define `codigo_pessoa` e `usuario_id` com `onDelete('cascade')`.
  - `resources/views/livewire/profile/delete-user-form.blade.php:14-22` permite que o usuário autenticado exclua a própria conta.
  - `app/Filament/Resources/Users/Tables/UsersTable.php:75-78` oferece `DeleteBulkAction`.
  - `app/Policies/UserPolicy.php:44-52` impede a autoexclusão apenas no delete individual; `deleteAny` (`:73-76`) não verifica autoexclusão nem último admin.
  - `Pessoa` e `Vinculo` não têm exclusão lógica; uma exclusão direta de pessoa remove `lancamentos` e `cota_especiais` por cascata.
- **Descrição:** O histórico de lançamentos é o livro-razão do sistema. As chaves estrangeiras em cascata tratam lançamentos como dados dependentes descartáveis. Excluir um operador apaga todos os lançamentos que ele registrou; excluir uma pessoa apaga todo o consumo dela. A auditoria de `Lancamento` não impede a exclusão física; o registro de auditoria pode permanecer, mas o dado original e a possibilidade de reconciliação desaparecem.
- **Cenário de abuso:** Operador com desvio de conduta exclui a própria conta após conceder créditos a conhecidos; administrador exclui um operador para eliminar evidências; administrador exclui uma pessoa para “limpar” o histórico.
- **Pré-condições:** acesso a `/profile` (qualquer autenticado) ou ao painel Admin (Admin); confirmação de senha no caso do perfil.
- **Impacto técnico:** perda irreversível de transações, quebra de FKs históricas, relatórios e saldos passados irreproduzíveis.
- **Impacto de negócio:** impossibilidade de auditar consumo, cobrar ou defender a instituição; perda de prova; risco legal e reputacional.
- **Controles existentes:** auditoria de `Lancamento` no momento da criação/alteração; `UserPolicy::delete` impede autoexclusão individual; UI de extrato é somente leitura.
- **Controles ausentes:** FK com `RESTRICT`/`NO ACTION`; exclusão lógica/desativação; retenção legal; bloqueio de exclusão de usuário com lançamentos; separação entre identidade e transação; anonimização em vez de exclusão.
- **Remediação:** alterar as FKs para impedir cascata; nunca excluir fisicamente usuários/pessoas com histórico; implementar desativação e anonimização preservando o lançamento; manter o `usuario_id` como referência histórica (ou snapshot do operador); criar política de retenção e legal hold; restringir exclusão a um fluxo auditado e com aprovação; corrigir `deleteAny`.
- **Mitigação temporária:** remover/desabilitar a autoexclusão de conta; remover `DeleteBulkAction` de usuários; monitorar exclusões; fazer backup antes de qualquer exclusão; congelar contas em vez de apagar.
- **Esforço/dependências:** M; depende de decisão de retenção e de modelo de identidade.
- **Prioridade:** P0.
- **Critérios de verificação:** tentativa de excluir usuário com lançamentos é bloqueada ou converte-se em desativação; contagem de lançamentos permanece; extrato continua exibindo o operador histórico; exclusão de pessoa não remove lançamentos; auditoria registra a desativação.
- **Testes sugeridos:** teste de FK; teste de autoexclusão; teste de bulk delete; teste de desativação/anonymização; teste de reconciliação.

### F-03 — Canal administrativo paralelo do pacote Senha Única: impersonação e gestão de usuários fora do RBAC

- **Categoria:** Autorização, identidade, segregação de funções, auditoria.
- **Severidade técnica:** Alta a crítica (CVSS 8.8 aproximado; `AV:N/AC:L/PR:H/UI:N/S:U/C:H/I:H/A:N`), dependendo de `SENHAUNICA_ADMINS`/gate.
- **Risco de negócio:** Crítico se o canal estiver ativo e acessível; Alto se estiver inacessível por falta de configuração.
- **Probabilidade:** Média (depende de configuração de ambiente e de o gate `admin` do guard `senhaunica` ser concedido).
- **Confiança:** Confirmado no código; exposição efetiva necessita verificação.
- **Componentes/evidência:**
  - `vendor/uspdev/senhaunica-socialite/routes/web.php:6-18` registra `/login`, `/callback`, `/logout`, `/loginas` (GET/POST), `/undologinas` e o resource `/senhaunica-users` (find, jsonModalContent, updatePermission).
  - `vendor/uspdev/senhaunica-socialite/config/config.php:45-69` tem `routes=true`, `userRoutes='senhaunica-users'`, `onlyLocalUsers=false` e `admins` lido de `SENHAUNICA_ADMINS`.
  - `vendor/uspdev/senhaunica-socialite/src/Http/Controllers/UserController.php:27-40` permite assumir a identidade de qualquer `codpes` via `loginAs`, autorizado pelo gate `admin` do guard `senhaunica`.
  - `UserController.php:170-230` (gestão de permissões/roles) e `destroy` removem usuários; o pacote não integra a auditoria da aplicação.
  - `vendor/uspdev/senhaunica-socialite/src/Http/Controllers/SenhaunicaController.php:57-58` contém `TODO` de log de login/logout.
  - Não há `config/senhaunica.php` publicado nem documentação do CotaG sobre esses endpoints.
- **Descrição:** O pacote cria um segundo sistema de administração, independente do `Admin` do Spatie. Quem estiver em `SENHAUNICA_ADMINS` ou receber a permissão hierárquica `admin` no guard `senhaunica` pode assumir a identidade de qualquer pessoa, alterar permissões/roles de aplicação e excluir usuários. Isso não passa pelas policies, pelo painel Filament nem pela auditoria de `User`/`Role`, e não é documentado no README/deploy.
- **Cenário de abuso:** Um administrador do pacote (ou atacante que obtenha essa permissão) usa `/loginas` para assumir a identidade de um operador, registra créditos, exclui usuários ou altera roles; a aplicação não registra a impersonação nem a alteração de pivô.
- **Pré-condições:** rotas do pacote ativas (default), gate `admin` concedido via `SENHAUNICA_ADMINS` ou permissão `senhaunica`; acesso autenticado.
- **Impacto técnico:** bypass completo do RBAC da aplicação, impersonação, alteração de privilégios e exclusão de contas.
- **Impacto de negócio:** fraude, perda de histórico, responsabilização impossível, conflito com a política de acesso institucional.
- **Controles existentes:** gate `admin` do pacote; `canAccessPanel` do Filament restringe o painel, mas não essas rotas.
- **Controles ausentes:** desabilitação das rotas não usadas; mapeamento para o RBAC da aplicação; auditoria de impersonação; revisão de `SENHAUNICA_ADMINS`; documentação; alertas.
- **Remediação:** decidir se o recurso é necessário. Se não for, desabilitar as rotas do pacote e remover a dependência de administração paralela. Se for, protegê-las com o mesmo RBAC da aplicação, exigir MFA/reauth, registrar impersonação e alterações de papel em trilha imutável, revisar `SENHAUNICA_ADMINS` periodicamente e documentar.
- **Mitigação temporária:** garantir `SENHAUNICA_ADMINS` vazio; remover permissão `admin` do guard `senhaunica`; bloquear `/loginas` e `/senhaunica-users` no proxy; monitorar acessos.
- **Esforço/dependências:** M; depende do produto e da política de identidade USP.
- **Prioridade:** P0/P1.
- **Critérios de verificação:** `route:list` não mostra rotas não aprovadas; usuário não autorizado recebe 403; impersonação e alteração de papel geram auditoria; não existe administrador de pacote fora do RBAC.
- **Testes sugeridos:** teste de rota 403; teste de impersonação auditada; teste de alteração de role via pacote; teste de `SENHAUNICA_ADMINS` vazio.

### F-04 — Debug OAuth grava dados pessoais por padrão, sem retenção

- **Categoria:** Privacidade, dados, configuração.
- **Severidade técnica:** Alta (CVSS 7.5 aproximado; exposição de PII em repouso).
- **Risco de negócio:** Alto.
- **Probabilidade:** Alta (default true; produção não define a variável).
- **Confiança:** Confirmado no código e em artefato local.
- **Componentes/evidência:**
  - `vendor/uspdev/senhaunica-socialite/config/config.php:68-69`: `debug => (bool) env('SENHAUNICA_DEBUG', true)`.
  - `vendor/uspdev/senhaunica-socialite/src/Server.php:61-62`: grava `debug/oauth/{loginUsuario}.json` com o retorno OAuth.
  - `.env.example` não define `SENHAUNICA_DEBUG`; `docker-compose.prod.yml:65-68` só define `SENHAUNICA_KEY`, `SENHAUNICA_SECRET` e `SENHAUNICA_CALLBACK_ID`.
  - Há um arquivo não versionado em `storage/app/private/debug/oauth/` com chaves de nome, e-mails, telefone, vínculo e `wsuserid` (valores não reproduzidos).
  - `vendor/uspdev/senhaunica-socialite/src/Traits/HasSenhaunica.php:148-152` expõe a existência do JSON; a rota `getJsonModalContent` permite visualizá-lo para administradores do pacote.
- **Descrição:** O debug grava PII de cada login OAuth no filesystem, por padrão, sem prazo de retenção, sem criptografia específica e sem base legal documentada. Em produção, o diretório está no bind mount `./storage`, que também é copiado em backup. O arquivo local encontrado demonstra que o comportamento ocorre.
- **Cenário de abuso:** acesso ao host/volume/backup; administrador do pacote visualiza JSON; vazamento por backup ou cópia de storage; retenção indefinida após o desligamento do usuário.
- **Pré-condições:** `SENHAUNICA_DEBUG=true` (default) e pelo menos um login OAuth; acesso ao storage/backup.
- **Impacto técnico:** PII em repouso, fora do banco e fora das políticas de acesso/auditoria.
- **Impacto de negócio:** não conformidade com LGPD, exposição de dados de contato/vínculo, dano reputacional.
- **Controles existentes:** storage fora de `public/`; permissões de arquivo do host; rota de visualização restrita ao gate do pacote.
- **Controles ausentes:** default seguro; retenção; purge; criptografia; inventário; base legal; acesso auditado.
- **Remediação:** definir `SENHAUNICA_DEBUG=false` em todos os ambientes; remover os arquivos existentes; incluir purge no deploy; restringir storage; documentar retenção; se o debug for necessário, usá-lo apenas em ambiente isolado, com prazo e acesso auditado.
- **Mitigação temporária:** purgar `storage/app/private/debug/oauth`; adicionar checagem de configuração no boot; monitorar criação de arquivos.
- **Esforço/dependências:** S; depende de mudança de configuração e limpeza.
- **Prioridade:** P1.
- **Critérios de verificação:** login OAuth não cria arquivo; diretório vazio após purge; `SENHAUNICA_DEBUG=false` no ambiente; backup não contém JSON.
- **Testes sugeridos:** teste de configuração; teste de login sem gravação; teste de purge.

### F-05 — Tráfego do Replicado sem criptografia e com validação de certificado desligada

- **Categoria:** Criptografia, rede, terceiros.
- **Severidade técnica:** Alta (CVSS 7.4 aproximado; interceptação de credenciais/PII em rede).
- **Risco de negócio:** Alto.
- **Probabilidade:** Média (depende de posição na rede e do caminho até o Replicado).
- **Confiança:** Confirmado na configuração; exploração necessita verificação de rede.
- **Componentes/evidência:**
  - `docker/production/freetds.conf.template:8` e `:16` definem `encryption = off`.
  - `vendor/uspdev/replicado/src/DB.php:34-36` monta DSN `dblib:host=...` (pdo_dblib), que usa o FreeTDS acima.
  - `config/database.php:114-126` define a conexão `replicado` com `encrypt => true` e `trust_server_certificate => true`; o `trust` desliga a validação do certificado.
  - `docs/DEPLOY_PORTAINER.md:380-384` confirma que o pacote usa `pdo_dblib` e que as consultas próprias usam `pdo_sqlsrv`.
- **Descrição:** Há dois caminhos para o Replicado: o pacote `uspdev/replicado` via `pdo_dblib`/FreeTDS com `encryption=off`, e as consultas próprias via `sqlsrv` com criptografia habilitada, mas sem validar o certificado do servidor. O primeiro transmite credenciais e resultados de consultas (incluindo e-mails e vínculos) sem criptografia; o segundo é vulnerável a MITM por não validar o certificado.
- **Cenário de abuso:** atacante na rede interna ou no caminho até o SQL Server captura credenciais do Replicado ou dados de pessoas; usa as credenciais para consultar/alterar a base corporativa, conforme permissões.
- **Pré-condições:** acesso à rede entre aplicação e Replicado; tráfego não criptografado ou certificado não validado.
- **Impacto técnico:** confidencialidade e integridade de credenciais e PII; possibilidade de respostas forjadas.
- **Impacto de negócio:** vazamento de dados pessoais, uso indevido de credenciais institucionais, não conformidade.
- **Controles existentes:** `sqlsrv` com `encrypt=true`; rede Docker privada; Replicado normalmente em rede institucional.
- **Controles ausentes:** TLS obrigatório com validação para ambos os drivers; segmentação/allowlist; rotação de credenciais; monitoramento de conexões.
- **Remediação:** habilitar TLS no FreeTDS e no `sqlsrv`, validar certificado/CA, desligar `trust_server_certificate`, documentar a versão mínima de TLS aceita pelo Replicado e obter exceção formal se o legado não permitir; restringir rede; rotacionar credenciais; monitorar.
- **Mitigação temporária:** segmentar a rede; limitar quem acessa o Replicado; usar conta de leitura; monitorar consultas; reduzir dados retornados.
- **Esforço/dependências:** M; depende da infraestrutura USP e do suporte a TLS no servidor legado.
- **Prioridade:** P1.
- **Critérios de verificação:** captura de tráfego em ambiente de teste mostra TLS; conexão com certificado inválido falha; `trust_server_certificate=false`; credenciais rotacionadas.
- **Testes sugeridos:** teste de conexão TLS; teste de certificado inválido; teste de falha de rede.

### F-06 — Ausência de fechamento mensal, versionamento e trilha imutável do saldo

- **Categoria:** Lógica de negócio, integridade, auditoria.
- **Severidade técnica:** N/A para CVSS (regra de negócio); equivalente a Alta.
- **Risco de negócio:** Crítico.
- **Probabilidade:** Alta (qualquer edição de cota/vínculo/cota especial altera o passado).
- **Confiança:** Confirmado.
- **Componentes/evidência:**
  - `app/Services/CotaService.php:51-69` calcula o saldo com a cota/vínculo atuais e os lançamentos do mês corrente.
  - `app/Models/Lancamento.php:73-77` filtra apenas ano/mês atuais; não há tabela de fechamento/snapshot.
  - `Cota` e `CotaEspecial` são editáveis no Filament (`CotaForm`, `CotaEspecialForm`) sem vigência; a auditoria registra a alteração, mas não congela o valor usado no passado.
  - `AGENTE.md:118-127` descreve a renovação implícita; não há processo de fechamento.
- **Descrição:** O saldo é uma função do estado atual, não um registro contábil. Se uma cota é alterada de 500 para 1.000, todos os meses passados passam a exibir saldo diferente; se um vínculo é removido, a cota base muda retroativamente. Não há snapshot por pessoa/mês, nem versão da regra de cota, nem imutabilidade. Isso impede reconciliar o que foi efetivamente autorizado em cada mês.
- **Cenário de abuso/falha:** administrador altera a cota de um vínculo para corrigir um mês e altera inadvertidamente todos os meses; auditoria não consegue provar qual era a cota no momento do débito; operador contesta histórico; cobrança/ressarcimento fica indefensável.
- **Pré-condições:** edição de cota, cota especial ou vínculo; consulta de histórico.
- **Impacto técnico:** histórico não reproduzível; relatórios inconsistentes.
- **Impacto de negócio:** perda de auditabilidade, risco jurídico, impossibilidade de cobrança/reconciliação, decisões erradas.
- **Controles existentes:** auditoria de alterações de `Cota`/`CotaEspecial`; `scopeMesAtual` limita o cálculo ao mês corrente.
- **Controles ausentes:** fechamento mensal; snapshot; versionamento com vigência; relatório imutável; política de reprocessamento; índice/consulta por período.
- **Remediação:** definir um fechamento mensal que grave, por pessoa, cota aplicada, créditos, débitos, saldo, timestamp e versão da regra; tornar o fechamento imutável; versionar cotas/vínculos com data de início/fim e aprovação; permitir reprocessamento apenas com trilha e aprovação; separar “saldo corrente” de “extrato fechado”.
- **Mitigação temporária:** exportar mensalmente o extrato e arquivar com hash; congelar edições de cotas após o fechamento; registrar motivo de alteração.
- **Esforço/dependências:** M/L; depende de definição contábil e do produto.
- **Prioridade:** P0.
- **Critérios de verificação:** após fechamento, alterar uma cota não muda o saldo fechado; é possível reproduzir o saldo de um mês passado; relatório inclui versão da regra e hash; teste de regressão.
- **Testes sugeridos:** teste de fechamento; teste de alteração retroativa; teste de recomposição; teste de imutabilidade.

### F-07 — Cota especial sem unicidade, vigência, motivo ou aprovação; soma de múltiplos registros

- **Categoria:** Lógica de negócio, integridade, autorização.
- **Severidade técnica:** Média/Alta (regra de negócio; CVSS não se aplica diretamente).
- **Risco de negócio:** Alto.
- **Probabilidade:** Média/Alta (duplicidade é possível pela UI e pelo banco).
- **Confiança:** Confirmado.
- **Componentes/evidência:**
  - `database/migrations/2025_10_14_164919_create_cota_especials_table.php:14-23` não define unicidade para `codigo_pessoa` nem check de valor.
  - `app/Services/CotaService.php:96-108` verifica existência e retorna a **soma** de todas as cotas especiais da pessoa.
  - `app/Models/Pessoa.php:89-92` declara `cotaEspecial()` como `hasOne`, o que pressupõe no máximo um registro; a regra do serviço contradiz o modelo.
  - `app/Filament/Resources/CotaEspecials/Schemas/CotaEspecialForm.php:15-69` não valida unicidade, vigência, motivo, solicitante ou aprovador; `minValue(1)` limita apenas o valor.
  - `AGENTE.md:110-116` e `docs/analise/03-regras-de-negocio.md:23-24` descrevem uma cota especial única.
- **Descrição:** Duas cotas especiais para a mesma pessoa somam, podendo dobrar ou triplicar a cota. Não há data de início/fim, motivo, autor ou aprovação. A cota especial também pode reduzir a cota regular, se for menor, sem regra explícita. O modelo `hasOne` e o serviço `sum` contam histórias diferentes.
- **Cenário de abuso/falha:** administrador cria duas cotas especiais para a mesma pessoa (por engano ou fraude); a pessoa recebe 2.000 em vez de 1.000; não há como saber por que ou quem autorizou; uma cota especial antiga continua valendo para sempre.
- **Pré-condições:** acesso ao painel Admin; criação/edição de cota especial.
- **Impacto técnico:** cota efetiva incorreta; inconsistência modelo/serviço.
- **Impacto de negócio:** concessão indevida, perda financeira, falta de responsabilização.
- **Controles existentes:** auditoria de `CotaEspecial`; validação de NUSP no Replicado; UI restrita ao painel.
- **Controles ausentes:** unique constraint; regra de “uma ativa por pessoa”; vigência; motivo/autor/aprovador; workflow de aprovação; política de redução.
- **Remediação:** definir a regra oficial (substituir vs. somar); criar unique parcial/ativa por pessoa; adicionar vigência, motivo, solicitante e aprovador; registrar em auditoria; exigir aprovação para valores acima de limite; alinhar `hasOne` e `getCotaEspecial`.
- **Mitigação temporária:** consulta de duplicidades; bloquear criação duplicada na UI; revisar cotas especiais existentes; congelar edições.
- **Esforço/dependências:** M; depende de decisão de negócio.
- **Prioridade:** P1.
- **Critérios de verificação:** segunda cota ativa para a mesma pessoa é rejeitada; cota expirada não é considerada; auditoria contém motivo/autor; teste de soma vs. substituição.
- **Testes sugeridos:** teste de unicidade; teste de vigência; teste de duplicidade; teste de autorização.

### F-08 — Operador concede crédito arbitrário, sem limite, origem financeira ou aprovação

- **Categoria:** Lógica de negócio, fraude, segregação.
- **Severidade técnica:** Média (CVSS não se aplica diretamente; controle de negócio).
- **Risco de negócio:** Alto.
- **Probabilidade:** Alta (a UI oferece a ação; não há limite).
- **Confiança:** Confirmado.
- **Componentes/evidência:**
  - `resources/views/livewire/lancamento/manage-lancamentos.blade.php` oferece “Registrar Crédito” para o operador.
  - `app/Livewire/Lancamento/ManageLancamentos.php:49` valida apenas `integer|min:1` para o valor; não há máximo, motivo, origem ou aprovação.
  - `app/Services/CotaService.php:25-38` cria o lançamento diretamente, sem limite ou verificação de política.
  - `database/migrations/2025_10_14_165401_create_lancamentos_table.php:16-18` não tem `motivo`, `origem`, `comprovante` ou `aprovador`.
  - `docs/analise/03-regras-de-negocio.md:37` e `README.md:31-33` tratam crédito como adição de saldo, sem regra de negócio.
- **Descrição:** O operador pode adicionar qualquer quantidade de cópias a qualquer pessoa, sem limite, justificativa, vínculo com pagamento ou aprovação. O crédito é indistinguível de uma concessão administrativa legítima. Não há reconciliação com caixa/pagamento.
- **Cenário de abuso:** operador credita 1.000.000 de cópias para um conhecido; o saldo é usado; não há comprovante; a auditoria mostra o operador, mas não o motivo; o operador pode excluir a própria conta para apagar o vínculo (F-02).
- **Pré-condições:** conta de operador com `operar-sistema`.
- **Impacto técnico:** saldo inflado; registros sem origem.
- **Impacto de negócio:** fraude interna, perda financeira, impossibilidade de cobrança.
- **Controles existentes:** auditoria de `Lancamento`; identificação do operador; permissão específica.
- **Controles ausentes:** limite por operador/pessoa/dia; motivo/origem/comprovante; aprovação maker-checker; alertas de anomalia; relatório de créditos.
- **Remediação:** definir política de crédito (quem, quanto, por quê, com qual documento); limites e aprovação; registrar origem/pagamento; segregação entre quem solicita e quem aprova; alertas para valores atípicos; revisão periódica.
- **Mitigação temporária:** reduzir o papel do operador a débito; migrar crédito para fluxo administrativo; monitorar créditos acima de limite; revisar créditos existentes.
- **Esforço/dependências:** M; depende de decisão de negócio e financeiro.
- **Prioridade:** P1.
- **Critérios de verificação:** operador não consegue exceder limite; crédito acima do limite exige aprovação; relatório reconcilia créditos com origem; auditoria contém motivo.
- **Testes sugeridos:** teste de limite; teste de aprovação; teste de segregação; teste de auditoria.

### F-09 — Cotas de produção são definidas por valores de “simulação” do seeder

- **Categoria:** Lógica de negócio, configuração, governança.
- **Severidade técnica:** N/A (regra de negócio); equivalente a Alta.
- **Risco de negócio:** Alto.
- **Probabilidade:** Alta se o deploy seguir a documentação.
- **Confiança:** Confirmado.
- **Componentes/evidência:**
  - `database/seeders/CotaSeeder.php:18-39` define `ALUNOGR=500`, `ESTAGIARIORH=100`, `DOCENTE=1000`, `ALUNOPOS=750` com comentários “Simulação” e “seu NUSP de teste”.
  - `docs/DEPLOY_PORTAINER.md:249-255` instrui executar `CotaSeeder` em produção.
  - `database/migrations/2025_10_14_164147_create_cotas_table.php:14-18` não tem unicidade por `tipo_vinculo`, vigência ou aprovador.
- **Descrição:** A política de cotas em produção é um artefato de desenvolvimento. Os valores não têm fonte normativa, data de vigência, aprovação ou cobertura de todos os vínculos; pessoas com vínculos não previstos recebem cota zero. O seeder usa `updateOrCreate` sem unique, podendo duplicar registros.
- **Cenário de falha:** produção é instalada com 500/100/1.000/750; a política institucional real é diferente; alunos/servidores recebem cota errada; não há registro de quem aprovou.
- **Pré-condições:** execução do `CotaSeeder` em produção; ausência de tabela oficial.
- **Impacto técnico:** cota incorreta para toda a comunidade.
- **Impacto de negócio:** conflito com a política institucional, perda/benefício indevido, reclamações, risco jurídico.
- **Controles existentes:** `CotaSeeder` é idempotente por `tipo_vinculo` na intenção; auditoria de `Cota` após edição.
- **Controles ausentes:** fonte oficial; aprovação; vigência; cobertura de vínculos; validação de valores; unique constraint.
- **Remediação:** substituir o seeder por importação de tabela oficial aprovada, com versão/vigência e responsável; manter seed apenas para desenvolvimento; validar cobertura de vínculos; registrar aprovação; revisar valores com o IME.
- **Mitigação temporária:** não rodar `CotaSeeder` em produção sem revisão; comparar valores com a política oficial; documentar a origem.
- **Esforço/dependências:** S/M; depende do IME.
- **Prioridade:** P1.
- **Critérios de verificação:** tabela de cotas em produção tem fonte/aprovação/vigência; todos os vínculos relevantes estão cobertos; nenhum valor de “simulação” permanece.
- **Testes sugeridos:** teste de importação; teste de cobertura; teste de vigência.

### F-10 — Vínculos locais obsoletos e falha parcial do Replicado geram cota errada ou zero silencioso

- **Categoria:** Integridade de dados, integração, lógica de negócio.
- **Severidade técnica:** Alta (regra de negócio; CVSS 7.5 se visto como indisponibilidade/integridade).
- **Risco de negócio:** Alto.
- **Probabilidade:** Alta (o fluxo local-first é o caminho principal).
- **Confiança:** Confirmado.
- **Componentes/evidência:**
  - `app/Services/ReplicadoService.php:77-91` retorna a pessoa local sem consultar o Replicado se ela existir.
  - `app/Livewire/Lancamento/ManageLancamentos.php:152-158` busca local primeiro; `:203-214` ignora a versão do Replicado quando o `codpes` já existe localmente.
  - `importarPessoa` (`ManageLancamentos.php:105-133`) só cria; não atualiza nome/vínculos de pessoa existente.
  - `ReplicadoService::buscarVinculos` (`:212-236`) captura exceção e retorna lista vazia sem propagar erro.
  - `CotaService::getCotaBase` (`:79-89`) retorna 0 quando não há vínculos.
- **Descrição:** Uma pessoa que perdeu ou ganhou vínculo continua com o vínculo antigo no banco local; o serviço não atualiza. Se o Replicado falhar ao buscar vínculos, o erro é engolido e a cota vira zero, sem aviso ao operador. O operador decide débito/crédito com base em cota errada.
- **Cenário de falha:** aluno formado mantém vínculo `ALUNOGR` e continua com cota; servidor transferido mantém cota antiga; Replicado indisponível retorna zero e o operador registra débito que deveria ser bloqueado/analisado.
- **Pré-condições:** pessoa já importada; mudança de vínculo; falha do Replicado.
- **Impacto técnico:** cota/saldo incorretos; dados locais divergentes da fonte.
- **Impacto de negócio:** consumo indevido, decisões erradas, conflito com a pessoa, perda financeira.
- **Controles existentes:** consulta ao Replicado para novos; cache local para desempenho; logs de erro.
- **Controles ausentes:** TTL/sincronização; atualização de vínculos; distinção entre “sem vínculo” e “falha”; fail-closed; alerta ao operador; registro de fonte/data.
- **Remediação:** sincronizar/atualizar vínculos na seleção ou por job com TTL; registrar fonte e timestamp; se o Replicado falhar, bloquear a operação ou exigir confirmação explícita, nunca assumir zero; manter histórico de vínculos; alertar.
- **Mitigação temporária:** forçar atualização manual antes de lançar; revisar vínculos; monitorar divergências; não usar cota zero sem confirmação.
- **Esforço/dependências:** M; depende do Replicado e da política de sincronização.
- **Prioridade:** P1.
- **Critérios de verificação:** vínculo removido no Replicado deixa de conceder cota após TTL; falha do Replicado impede lançamento ou exibe erro; log/auditoria registra fonte.
- **Testes sugeridos:** teste de vínculo removido; teste de falha parcial; teste de cota zero; teste de atualização.

### F-11 — Busca de pessoas no Replicado sem limite, com wildcard e N+1, permite DoS e coleta massiva

- **Categoria:** Disponibilidade, abuso, dados.
- **Severidade técnica:** Alta (CVSS 7.5 aproximado; `AV:N/AC:L/PR:L/UI:N/S:U/C:N/I:N/A:H`).
- **Risco de negócio:** Alto.
- **Probabilidade:** Alta (qualquer operador; conta comprometida).
- **Confiança:** Confirmado no código; impacto depende do volume do Replicado.
- **Componentes/evidência:**
  - `app/Services/ReplicadoService.php:169-197` não usa `TOP`/limite; retorna todos os nomes que casam com o `LIKE`.
  - `:171-172` transforma espaços em `%` e não escapa `%`/`_` do usuário; uma entrada de 3 caracteres pode virar um padrão amplo.
  - `:183-189` executa uma consulta de vínculos por pessoa (N+1).
  - `app/Livewire/Lancamento/ManageLancamentos.php:141-147` exige apenas 3 caracteres; `:174` chama a busca por nome.
  - `routes/web.php:19-25` não aplica throttle específico à busca.
- **Descrição:** O operador pode disparar uma busca que retorna milhares de pessoas e, para cada uma, uma consulta adicional ao Replicado. Isso pode saturar a conexão legada, aumentar custo/latência e permitir coleta massiva de nomes/NUSP. O wildcard não é escapado, então `%%%` ou `___` ampliam o resultado.
- **Cenário de abuso:** operador mal-intencionado busca `%%%` ou um sobrenome comum; o Replicado executa uma consulta ampla e milhares de consultas de vínculo; a gráfica fica lenta/indisponível; os dados retornados são raspados.
- **Pré-condições:** conta com `operar-sistema`; acesso a `/lancamento`.
- **Impacto técnico:** DoS no Replicado e na aplicação; uso excessivo de recurso; exposição de dados.
- **Impacto de negócio:** indisponibilidade do atendimento, custo, incidente de dados.
- **Controles existentes:** autenticação/permissão; validação mínima de 3 caracteres; timeout de rede do driver.
- **Controles ausentes:** `TOP`/limite; escape de wildcard; paginação; throttle; batch de vínculos; cache; timeout/circuit breaker; alerta.
- **Remediação:** limitar resultados (por exemplo, 20–50), escapar `%`/`_`, exigir critério mais específico, paginar, buscar vínculos em lote, cachear, aplicar rate limit por usuário/IP e timeout; registrar buscas anômalas.
- **Mitigação temporária:** bloquear wildcards; reduzir limite; monitorar volume; restringir horários.
- **Esforço/dependências:** S/M; depende do Replicado.
- **Prioridade:** P1.
- **Critérios de verificação:** busca retorna no máximo N; `%%%` é rejeitado; uma busca comum não gera N+1; rate limit responde 429; latência dentro do alvo.
- **Testes sugeridos:** teste de limite; teste de wildcard; teste de carga; teste de N+1.

### F-12 — Tipo de lançamento controlado pelo cliente e ausência de idempotência

- **Categoria:** Validação, lógica de negócio, integridade.
- **Severidade técnica:** Média/Alta (CVSS 5.4–6.5 aproximado).
- **Risco de negócio:** Alto.
- **Probabilidade:** Média (requer cliente manipulado ou duplo submit).
- **Confiança:** Confirmado no código; exploração de propriedade Livewire necessita verificação.
- **Componentes/evidência:**
  - `app/Livewire/Lancamento/ManageLancamentos.php:56-57` declara `#[Rule('required|integer|in:0,1')]` para `tipoLancamento`.
  - `:264-283` valida apenas `valorLancamento` com `validateOnly('valorLancamento')`; o tipo não é revalidado.
  - `CotaService::registrarDebitoOuCredito` (`:25-38`) aceita qualquer inteiro e grava.
  - `database/migrations/2025_10_14_165401_create_lancamentos_table.php:17` não tem check constraint para `tipo_lancamento`.
  - `salvarLancamento` não usa token/idempotency key; um retry/duplo clique pode criar dois lançamentos.
- **Descrição:** As propriedades públicas do Livewire são controláveis pelo cliente. Um operador pode chamar `abrirModalLancamento` com tipo arbitrário ou definir `tipoLancamento` diretamente e salvar; como só o valor é validado, o tipo inválido chega ao banco. Tipos diferentes de 0/1 são exibidos como “Crédito” na UI, mas ignorados no cálculo de saldo, criando transações “fantasma”. A ausência de idempotência permite duplicação.
- **Cenário de abuso:** operador registra tipo 2 para criar um lançamento que aparece no extrato como crédito mas não altera saldo; ou reenvia a mesma requisição e duplica um débito; ou usa um script para inserir tipos arbitrários.
- **Pré-condições:** conta de operador; capacidade de manipular requisições Livewire ou duplo submit.
- **Impacto técnico:** integridade do livro-razão; inconsistência entre extrato e saldo; duplicidade.
- **Impacto de negócio:** relatórios errados, fraude, perda financeira, auditoria confusa.
- **Controles existentes:** regra declarada no atributo; validação do valor; auditoria.
- **Controles ausentes:** validação do tipo no ponto de escrita; check no banco; idempotência; trava de UI; teste de replay.
- **Remediação:** validar tipo e valor no serviço/ponto de escrita, não apenas na UI; usar enum/constraint; exigir idempotency key única por operação; desabilitar botão durante submit; registrar correlação; criar fluxo de correção em vez de duplicar.
- **Mitigação temporária:** validar tipo no servidor antes de salvar; adicionar check no banco; monitorar tipos fora de 0/1; desabilitar duplo clique.
- **Esforço/dependências:** S/M.
- **Prioridade:** P1.
- **Critérios de verificação:** tipo 2/-1 é rejeitado; retry da mesma requisição cria um único lançamento; extrato e saldo concordam; teste de replay.
- **Testes sugeridos:** teste de tipo inválido; teste de idempotência; teste de concorrência; teste de UI.

### F-13 — Auditoria incompleta, mutável e sem retenção

- **Categoria:** Auditoria, rastreabilidade, conformidade.
- **Severidade técnica:** Média/Alta (CVSS 6.5 aproximado; integridade de logs).
- **Risco de negócio:** Alto.
- **Probabilidade:** Alta (lacunas estruturais).
- **Confiança:** Confirmado no código; algumas lacunas necessitam verificação de runtime.
- **Componentes/evidência:**
  - `config/permission.php:122` define `events_enabled => false`; `RoleObserver`/`PermissionObserver` registram apenas atributos do modelo, não o pivô de roles/permissões.
  - `User` é auditable, mas `syncRoles`/`syncPermissions` não altera atributos de `User`; a concessão/revogação de papel não gera auditoria de `User`.
  - `config/audit.php:197` define `console => false`; alterações via CLI/tinker não são auditadas.
  - `config/audit.php:59-64` audita apenas `created/updated/deleted/restored`, não login/logout/falha/impersonação.
  - `Pessoa` e `Vinculo` não usam `Auditable`; imports, atualizações e remoções de vínculos não são auditados.
  - `audits` (`migration:2025_10_06_174727_create_audits_table.php`) não tem hash/encadeamento, retenção ou WORM; `AuditPolicy` bloqueia edição pela UI, mas o banco permite alteração.
  - `vendor/uspdev/senhaunica-socialite/src/Http/Controllers/SenhaunicaController.php:58` tem `TODO` de log de login/logout.
- **Descrição:** A auditoria cobre parte das alterações de modelos, mas não cobre o que mais importa para segurança: concessão de privilégios, impersonação, importação/alteração de vínculos, logins, exclusões e alterações via CLI. O registro é gravado no mesmo banco e pode ser alterado por quem tem acesso; não há retenção definida.
- **Cenário de abuso:** administrador concede `Admin` a um cúmplice via UI/CLI; não há registro da concessão; depois remove o papel; não há prova. Administrador do pacote usa `/loginas`; não há registro. Operador importa vínculo falso; não há auditoria.
- **Pré-condições:** acesso administrativo ou CLI; banco acessível.
- **Impacto técnico:** não repúdio fraco; trilha incompleta; logs alteráveis.
- **Impacto de negócio:** impossibilidade de responsabilizar, defesa jurídica fraca, não conformidade.
- **Controles existentes:** auditoria de modelos de negócio; política de UI somente leitura; IP/user agent/URL.
- **Controles ausentes:** auditoria de pivô, login/logout, impersonação, CLI, imports; hash/WORM; retenção; separação de acesso; alertas.
- **Remediação:** auditar eventos de autorização e identidade; registrar concessão/revogação de papéis e permissões; registrar impersonação e imports; habilitar auditoria de console; usar armazenamento append-only ou hash chain; definir retenção e legal hold; restringir acesso ao banco de auditoria; monitorar.
- **Mitigação temporária:** exportar auditoria para armazenamento imutável; revisar concessões; monitorar `model_has_roles`; registrar mudanças via processo manual.
- **Esforço/dependências:** M/L; depende de arquitetura de logging e retenção.
- **Prioridade:** P1.
- **Critérios de verificação:** conceder/revogar papel gera evento; `/loginas` gera evento; alteração via CLI gera evento; tentativa de alterar auditoria é detectada; retenção configurada.
- **Testes sugeridos:** teste de role attach/detach; teste de impersonação; teste de CLI; teste de integridade.

### F-14 — Ausência de segregação de funções e proteção do último administrador

- **Categoria:** Autorização, segregação, disponibilidade.
- **Severidade técnica:** Média/Alta (CVSS 6.5–7.2 aproximado).
- **Risco de negócio:** Alto.
- **Probabilidade:** Média/Alta.
- **Confiança:** Confirmado.
- **Componentes/evidência:**
  - `database/seeders/RoleSeeder.php:34-39` concede `operar-sistema` a `Admin` e `Operador`; o Admin pode operar `/lancamento`.
  - `app/Providers/Filament/AdminPanelProvider.php:124` restringe o painel a `ADM|Admin`; as policies dão ao Admin controle de usuários, papéis, permissões, cotas, cotas especiais, extrato e auditoria.
  - `UserForm` permite editar os próprios papéis; `UserPolicy::update` (`:36-39`) não impede autoalteração.
  - `UsersTable:75-78` permite `DeleteBulkAction`; `UserPolicy::deleteAny` (`:73-76`) não protege autoexclusão/último admin.
  - `RolePolicy::delete` (`:45-53`) protege apenas o delete individual da role `Admin`; `deleteAny` (`:74-77`) e `forceDelete` (`:66-69`) não protegem.
- **Descrição:** Um único `Admin` pode configurar cotas, conceder créditos, operar lançamentos, gerenciar usuários/papéis e ler auditoria, sem segunda aprovação. Pode remover o próprio papel ou excluir em massa o último administrador, causando lockout. Não há separação entre “quem configura”, “quem opera” e “quem audita”.
- **Cenário de abuso/falha:** admin desonesto altera cota e credita a si/conhecidos; não há outro aprovador. Admin remove o próprio papel por engano e ninguém consegue entrar. Bulk delete remove todos os admins.
- **Pré-condições:** conta Admin.
- **Impacto técnico:** concentração de poder; lockout; alterações não revisadas.
- **Impacto de negócio:** fraude, indisponibilidade administrativa, perda de controle.
- **Controles existentes:** autenticação; auditoria parcial; `UserPolicy::delete` impede autoexclusão individual.
- **Controles ausentes:** separação de papéis; maker-checker; proteção do último admin em todas as operações; revisão de acesso; alerta.
- **Remediação:** separar papéis (operador, gestor de cotas, auditor, administrador de identidade); exigir aprovação para créditos/quotas; impedir autodemissão e remoção do último admin; proteger `Admin` em bulk/force delete; revisar acessos periodicamente.
- **Mitigação temporária:** remover bulk delete; congelar edições de papéis; manter pelo menos dois admins; monitorar.
- **Esforço/dependências:** M; depende do modelo organizacional.
- **Prioridade:** P1.
- **Critérios de verificação:** último admin não pode ser removido; autoedição de papel bloqueada; operador não configura cota; auditor não altera dados; aprovação exigida.
- **Testes sugeridos:** teste de último admin; teste de autodemissão; teste de bulk; teste de separação.

### F-15 — Identidade fragmentada: `codpes` não único, registro público e vínculo SSO por `codpes`

- **Categoria:** Identidade, autenticação, integridade.
- **Severidade técnica:** Alta (CVSS 7.1 aproximado).
- **Risco de negócio:** Alto.
- **Probabilidade:** Média/Alta.
- **Confiança:** Confirmado no schema/código; exploração necessita verificação.
- **Componentes/evidência:**
  - `database/migrations/2025_04_11_133701_update_senhaunica_users_table.php:17-20` adiciona `codpes` sem unique.
  - `resources/views/livewire/pages/auth/register.blade.php:65-131` não verifica unicidade de `codpes`; permite múltiplos e-mails para o mesmo NUSP (cada um validado no Replicado).
  - `vendor/uspdev/senhaunica-socialite/src/Http/Controllers/SenhaunicaController.php:50-57` faz `firstOrNew(['codpes' => ...])`, escolhendo um registro arbitrário se houver duplicados; sobrescreve e-mail/nome.
  - `resources/views/livewire/profile/update-profile-information-form.blade.php:27-41` permite trocar e-mail sem reautenticação e zera a verificação.
  - `register.blade.php:66-91` confia em `sou_da_usp` controlado pelo cliente para exigir/validar `codpes`.
- **Descrição:** O identificador institucional não é único no banco. Uma pessoa pode ter várias contas, cada uma com um e-mail validado; o SSO escolhe uma delas por `codpes`. Um atacante pode pré-registrar o e-mail de outra pessoa, criar colisão e causar DoS/tomada de identidade; a troca de e-mail no perfil não exige reautenticação e pode desvincular a identidade USP do e-mail.
- **Cenário de abuso:** atacante registra o e-mail público e o NUSP de uma pessoa antes do primeiro login dela; o SSO encontra a conta pré-existente; a vítima pode ficar sem acesso ou compartilhar a conta; atacante altera o e-mail do perfil para um e-mail que controla e verifica, mantendo o papel.
- **Pré-condições:** registro público; conhecimento de NUSP/e-mail; ou conta comprometida.
- **Impacto técnico:** duplicidade de identidade; sequestro de conta; vínculo errado de papel.
- **Impacto de negócio:** acesso indevido, erosão de confiança, suporte, risco legal.
- **Controles existentes:** validação de e-mail no Replicado para `usp_user`; verificação de e-mail; `firstOrNew` por `codpes`.
- **Controles ausentes:** unique de `codpes`; fluxo de vinculação explícito; reautenticação para troca de e-mail; restrição de registro; não confiar em boolean do cliente.
- **Remediação:** tornar `codpes` único (com tratamento de legado); vincular SSO a uma única conta; exigir convite/aprovação para registro; não confiar em `sou_da_usp`; validar sempre no servidor; exigir senha/reauth para trocar e-mail; registrar vinculação.
- **Mitigação temporária:** revisar duplicidades; bloquear registro; forçar verificação; monitorar mudanças de e-mail.
- **Esforço/dependências:** M; depende de migração de dados e política de identidade.
- **Prioridade:** P1.
- **Critérios de verificação:** não é possível criar duas contas com o mesmo `codpes`; SSO encontra uma única conta; troca de e-mail exige reauth; bypass de `sou_da_usp` falha.
- **Testes sugeridos:** teste de unique; teste de SSO; teste de troca de e-mail; teste de registro.

### F-16 — Autenticação local sem MFA, cookie sem `Secure`, proxy confiado de forma ampla e headers incompletos

- **Categoria:** Autenticação, sessão, configuração, rede.
- **Severidade técnica:** Média (CVSS 5.3–6.5 aproximado).
- **Risco de negócio:** Alto.
- **Probabilidade:** Média.
- **Confiança:** Confirmado na configuração; impacto depende da rede/NPM.
- **Componentes/evidência:**
  - `routes/auth.php:11-12` mantém login local por e-mail/senha; `SenhaunicaController` mantém SSO; não há MFA no `composer.json`/config.
  - `app/Filament/Resources/Users/Schemas/UserForm.php:32-37` permite senha sem `Password::defaults()`; apenas `maxLength(255)`.
  - `config/session.php:172` usa `env('SESSION_SECURE_COOKIE')` sem default; `.env.example` e `docker-compose.prod.yml:44-49` não definem `SESSION_SECURE_COOKIE`.
  - `bootstrap/app.php:20-24` confia em qualquer proxy (`at: '*'`) para `X-Forwarded-*`.
  - `docker/production/nginx.conf:35-37` envia `X-Frame-Options`, `X-Content-Type-Options` e `X-XSS-Protection`, mas não HSTS, CSP, Referrer-Policy ou Permissions-Policy.
- **Descrição:** O login local permite senha para qualquer conta, inclusive administradores, sem MFA; a Senha Única pode ter MFA, mas o login local contorna esse controle. O cookie de sessão pode ser enviado por HTTP porque `Secure` não está garantido. `trustProxies('*')` permite que qualquer host na rede spoofe IP/proto/host, afetando rate limit, URLs e logs. Faltam headers de defesa em profundidade.
- **Cenário de abuso:** atacante obtém senha local de admin (phishing/brute force distribuído) e entra sem MFA; ou intercepta cookie em HTTP; ou, na rede Docker, spoofa `X-Forwarded-For` para contornar o rate limit e `X-Forwarded-Host` para envenenar links.
- **Pré-condições:** login local habilitado; rede/posição para interceptação/spoofing.
- **Impacto técnico:** tomada de conta; bypass de rate limit; links envenenados.
- **Impacto de negócio:** acesso indevido, fraude, incidente.
- **Controles existentes:** rate limit; hash; `HttpOnly`/`SameSite`; `APP_DEBUG=false` em produção; NPM termina TLS.
- **Controles ausentes:** MFA; `Secure`; proxy restrito; HSTS/CSP/Referrer; política de senha para admins; reauth.
- **Remediação:** exigir HTTPS e `Secure`; configurar HSTS no NPM; restringir proxies a IPs/CIDR do NPM; exigir MFA para Admin/Operador; desabilitar login local para privilegiados ou aplicar política; senha forte para contas criadas por admin; adicionar CSP/Referrer/Permissions; encurtar sessão.
- **Mitigação temporária:** forçar HTTPS no NPM; definir `SESSION_SECURE_COOKIE=true`; restringir proxy; monitorar logins.
- **Esforço/dependências:** M; depende de NPM/SSO.
- **Prioridade:** P1/P2.
- **Critérios de verificação:** cookie tem `Secure`; HTTP redireciona; XFF spoofado não altera IP; MFA exigido; headers presentes.
- **Testes sugeridos:** teste de cookie; teste de proxy; teste de MFA; teste de headers.

### F-17 — Open redirect no login Senha Única e ausência de throttle no registro/reenvio

- **Categoria:** Autenticação, abuso, phishing.
- **Severidade técnica:** Média (CVSS 6.1 para open redirect).
- **Risco de negócio:** Médio.
- **Probabilidade:** Média.
- **Confiança:** Confirmado.
- **Componentes/evidência:**
  - `vendor/uspdev/senhaunica-socialite/src/Http/Controllers/SenhaunicaController.php:15-29` aceita `redirect` como string e o guarda na sessão.
  - `:64-68` redireciona para o valor sem validar mesma origem.
  - `routes/auth.php:7-19` não aplica throttle ao registro; `resources/views/livewire/pages/auth/verify-email.blade.php` reenvia verificação sem throttle.
  - `app/Livewire/Forms/LoginForm.php:50-65` limita 5 tentativas por e-mail+IP, não por IP global.
- **Descrição:** Um link `/login?redirect=https://atacante` faz o usuário autenticar e ser enviado ao site do atacante após o SSO, facilitando phishing. Registro e reenvio de verificação podem ser usados para enumerar e-mails, spammar e consultar o Replicado sem limite.
- **Cenário de abuso:** atacante envia link de login com redirect externo; vítima autentica e é redirecionada para página falsa que pede dados; ou automatiza registro para enumerar e-mails e sobrecarregar o Replicado.
- **Pré-condições:** vítima clica no link; ou acesso ao registro.
- **Impacto técnico:** redirecionamento aberto; abuso de recurso; enumeração.
- **Impacto de negócio:** phishing, dano reputacional, custo.
- **Controles existentes:** validação de string; login local com rate limit.
- **Controles ausentes:** allowlist de redirect; throttle de registro/reenvio; captcha; respostas genéricas.
- **Remediação:** aceitar apenas caminhos relativos same-origin; rejeitar esquema/host externo; throttle por IP/e-mail; captcha; limitar reenvio; monitorar.
- **Mitigação temporária:** remover parâmetro `redirect` ou bloquear domínios externos no proxy; aplicar rate limit.
- **Esforço/dependências:** S/M.
- **Prioridade:** P2.
- **Critérios de verificação:** redirect externo é rejeitado; registro retorna 429 após N; reenvio limitado; enumeração não diferencia.
- **Testes sugeridos:** teste de open redirect; teste de throttle; teste de enumeração.

### F-18 — Defaults de exemplo inseguros e e-mail em log podem expor tokens

- **Categoria:** Configuração, segredos, autenticação.
- **Severidade técnica:** Média/Alta (CVSS 6.5–7.5 dependendo da exposição).
- **Risco de negócio:** Alto (se aplicado em produção).
- **Probabilidade:** Média (depende de seguir o exemplo sem override).
- **Confiança:** Confirmado na configuração; impacto condicional.
- **Componentes/evidência:**
  - `.env.example:3-4` tem `APP_KEY=` vazio e `APP_DEBUG=true`; `:34` tem `DB_PASSWORD` com placeholder fraco (valor redigido); `:38-42` não define `SESSION_SECURE_COOKIE`; `:52` define `MAIL_MAILER=log`.
  - `docker-compose.prod.yml:56` usa `MAIL_MAILER` default `log`; `:20` default `APP_DEBUG=false`; `:42` default `DB_PASSWORD` com placeholder fraco (valor redigido).
  - `resources/views/livewire/pages/auth/forgot-password.blade.php` e `verify-email` enviam links/tokens; `config/mail.php` permite `log`.
  - `resources/views/livewire/pages/auth/reset-password.blade.php` coloca token na URL.
- **Descrição:** O exemplo de ambiente é de desenvolvimento, mas o README manda copiá-lo. Se alguém implantar sem override, terá debug ligado, senha de banco padrão e e-mail em log. Tokens de reset/verificação e PII podem ser gravados em `storage/logs`, acessível a operadores/host/backup.
- **Cenário de abuso:** deploy apressado usa `.env.example`; atacante lê stack trace ou acessa log; token de reset é usado para tomar conta; senha de banco padrão é usada.
- **Pré-condições:** deploy sem override; acesso a logs.
- **Impacto técnico:** exposição de segredos/tokens; tomada de conta.
- **Impacto de negócio:** incidente, fraude, não conformidade.
- **Controles existentes:** compose de produção tem defaults seguros para `APP_DEBUG`/`APP_ENV`; documentação alerta.
- **Controles ausentes:** example seguro; validação de boot; SMTP obrigatório; mascaramento de tokens em log.
- **Remediação:** separar `.env.example` de produção; falhar o boot se `APP_DEBUG=true`/senha padrão em produção; exigir SMTP com TLS; nunca logar corpo de e-mail em produção; usar secret manager; documentar checklist.
- **Mitigação temporária:** revisar env de produção; desligar debug; configurar SMTP; limpar logs.
- **Esforço/dependências:** S; depende de infra.
- **Prioridade:** P1/P2.
- **Critérios de verificação:** boot de produção rejeita defaults; reset não aparece em log; SMTP configurado; `APP_DEBUG=false`.
- **Testes sugeridos:** teste de configuração; teste de mailer; teste de log.

### F-19 — Nginx permite execução de PHP em `/storage` e não usa `try_files`/limite de extensão

- **Categoria:** Configuração de servidor, execução.
- **Severidade técnica:** Média (CVSS 6.5 aproximado; depende de upload/escrita).
- **Risco de negócio:** Médio/Alto se houver upload futuro.
- **Probabilidade:** Baixa no estado atual (não há upload), mas alta se a funcionalidade for adicionada.
- **Confiança:** Confirmado na configuração.
- **Componentes/evidência:**
  - `docker/production/nginx.conf:84-95` define `location ~ \.php$` antes de `:97-100`, que tenta negar `/storage/*.php`; a primeira regex vence, tornando a negação inalcançável.
  - `:84-95` não usa `try_files $uri =404` nem `fastcgi_split_path_info`.
  - `docker/production/php-fpm.conf` não define `security.limit_extensions`.
  - `:33` permite corpo de 100 MB.
- **Descrição:** Se um arquivo `.php` for gravado em `public/` ou `public/storage`, o Nginx o encaminha ao PHP-FPM. A regra de bloqueio de `/storage` está na ordem errada. Não há limitação de extensão no PHP-FPM. Hoje não há upload, mas qualquer feature futura de anexo/importação pode transformar isso em RCE.
- **Cenário de abuso:** atacante explora uma falha de upload/escrita para colocar `shell.php` em `storage` ou `public`; o Nginx executa.
- **Pré-condições:** capacidade de escrita em diretório servido; ausência de upload atual reduz a probabilidade.
- **Impacto técnico:** execução remota de código, acesso a banco/segredos.
- **Impacto de negócio:** comprometimento total.
- **Controles existentes:** `public/` como raiz; bloqueio de dotfiles; PHP-FPM como `www-data`.
- **Controles ausentes:** ordem correta de deny; `try_files`; `security.limit_extensions`; diretório de upload sem PHP; limite de corpo.
- **Remediação:** colocar deny de storage antes da regra PHP; usar `try_files $uri =404`; definir `security.limit_extensions=.php`; desabilitar PHP em diretórios de upload; reduzir `client_max_body_size`; validar uploads.
- **Mitigação temporária:** revisar config; monitorar arquivos `.php` em `public`/`storage`; não habilitar upload até corrigir.
- **Esforço/dependências:** S.
- **Prioridade:** P2.
- **Critérios de verificação:** `/storage/x.php` retorna 404; path-info bloqueado; extensão limitada; upload não executa.
- **Testes sugeridos:** teste de config Nginx; teste de path-info; teste de upload (em ambiente seguro).

### F-20 — Supply chain: builds não reprodutíveis, ações/imagens não pinadas, dependências EOL e licenças GPL

- **Categoria:** Cadeia de suprimentos, dependências, legal.
- **Severidade técnica:** Alta (CVSS 7.0 aproximado para componente vulnerável; risco de supply chain).
- **Risco de negócio:** Alto.
- **Probabilidade:** Média.
- **Confiança:** Confirmado para configuração/licenças; CVEs não verificadas.
- **Componentes/evidência:**
  - `docker/production/Dockerfile:16,90` usa `php:8.2-cli`/`php:8.2-fpm` sem digest.
  - `:42-43` e `:130-131` baixam `install-php-extensions` de `releases/latest` sem checksum.
  - `:60` e `:150` baixam o instalador do Composer de `getcomposer.org` sem verificação de assinatura.
  - `:63-64` adiciona repositório NodeSource por chave baixada em tempo de build.
  - `:106` executa `apt-get upgrade -y`, tornando o build não determinístico.
  - `.github/workflows/laravel.yml:19,22,29,35` usa actions por tag (`@v4`, `@v2`), não por SHA.
  - O CI não executa `composer audit`, `npm audit`, secret scanning, container scanning ou IaC scanning.
  - `composer.json:7` declara MIT; `composer.lock` contém `uspdev/replicado` (GPL-2.0-or-later), `uspdev/senhaunica-socialite` (GPL-2.0-or-later) e `uspdev/cache` (GPL-3.0-or-later).
  - PHP 8.2 está em fim de suporte de segurança em 2026-12-31; o CI usa Node 20.x, cuja linha 20 entra em EOL em abril de 2026 (verificar política oficial).
- **Descrição:** O build depende de artefatos móveis (`latest`) e tags de actions, sem verificação de integridade. Não há SCA/secret/container scan. A imagem usa PHP 8.2, próximo do fim do suporte de segurança, e Node 20 no CI. O projeto MIT incorpora dependências GPL, o que exige análise jurídica sobre distribuição.
- **Cenário de falha/abuso:** comprometimento do instalador `latest`/Composer/NodeSource; vulnerabilidade conhecida em PHP 8.2 após EOL; dependência GPL distribuída sem cumprir obrigações.
- **Pré-condições:** build/deploy; distribuição do código/imagem.
- **Impacto técnico:** imagem comprometida/vulnerável; build não reproduzível.
- **Impacto de negócio:** incidente de supply chain, não conformidade legal, dificuldade de auditoria.
- **Controles existentes:** lockfiles; `composer install` com `--no-dev` no build; imagem multi-stage; `npm ci`.
- **Controles ausentes:** pin por digest/SHA/versão; checksums/assinaturas; SCA/secret/container/IaC scan; SBOM; Dependabot/Renovate; plano de upgrade; revisão de licenças.
- **Remediação:** pinar imagens/actions/versões e verificar checksums; usar imagens oficiais; atualizar PHP/Node; adicionar SCA/secret/container/IaC scans e SBOM; revisar licenças GPL com jurídico; automatizar atualizações.
- **Mitigação temporária:** congelar versões; auditar dependências; revisar licenças; planejar upgrade.
- **Esforço/dependências:** M/L; depende de infra/legal.
- **Prioridade:** P1.
- **Critérios de verificação:** build reprodutível; CI falha em CVE crítica; SBOM gerado; licenças aprovadas; PHP/Node suportados.
- **Testes sugeridos:** rebuild determinístico; scan de imagem; auditoria de licenças.

### F-21 — Suíte de testes contraditória e CI sem controles de segurança

- **Categoria:** Testes, qualidade, assurance.
- **Severidade técnica:** Média/Alta (risco de regressão não detectada).
- **Risco de negócio:** Alto.
- **Probabilidade:** Alta.
- **Confiança:** Confirmado estaticamente; não executei a suíte.
- **Componentes/evidência:**
  - `tests/Feature/Services/ReplicadoServiceTest.php:19-37` chama `buscarPessoa`, método inexistente em `ReplicadoService`, e usa `User` com `codigo_pessoa`, campo não previsto no modelo/factory.
  - `tests/Feature/Services/CotaServiceTest.php:164-180` espera que crédito tipo 0 não seja contado, mas `CotaService.php:63-67` soma créditos e `README.md:33` define `Saldo = Cota + Créditos - Débitos`.
  - `tests/Feature/Auth/AuthenticationTest.php:40-57` espera redirecionamento para `/dashboard`, mas `resources/views/livewire/pages/auth/login.blade.php:25` redireciona para `/lancamento`.
  - `tests/Feature/Filament/ResourcesTest.php:30-46` usa a role `ADM` e espera criar cota, mas `CotaPolicy.php:29-32` só autoriza `Admin`/`Operador`.
  - `tests/Browser/LoginTest.php:76-80` também espera `/dashboard`.
  - `.phpunit.result.cache` registra 12 entradas em `defects` (incluindo `CotaServiceTest::test_lancamentos_de_credito_tipo_0_nao_sao_contados`, `ReplicadoServiceTest::*` e `ResourcesTest::*`), confirmando que a última execução não estava verde.
  - O CI (`.github/workflows/laravel.yml`) não executa Dusk, não executa SCA/secret/container/IaC scan e instala dependências sem `composer audit`.
- **Descrição:** Há testes que descrevem comportamento que o código não implementa; a suíte não é uma fonte confiável de regressão. O CI cobre estilo, análise estática e PHPUnit, mas não os controles de segurança que importam. Dusk não roda no CI.
- **Cenário de falha:** uma mudança que reintroduz exclusão em cascata, remove autorização ou quebra o saldo passa porque o teste correspondente está errado/ausente; o time acredita que está protegido.
- **Pré-condições:** qualquer alteração futura.
- **Impacto técnico:** regressões silenciosas.
- **Impacto de negócio:** incidentes, retrabalho, perda de confiança.
- **Controles existentes:** PHPUnit, Pint, Larastan, fakes, alguns testes de feature.
- **Controles ausentes:** testes alinhados à regra; cobertura de autorização, exclusão, idempotência, auditoria, privacidade; Dusk no CI; scans.
- **Remediação:** corrigir testes para o comportamento aprovado; adicionar testes de segurança e de regra; rodar Dusk; adicionar scans; bloquear merge com falha; medir cobertura de áreas críticas.
- **Mitigação temporária:** executar a suíte e triar falhas; não confiar no badge; adicionar testes manuais de segurança.
- **Esforço/dependências:** M.
- **Prioridade:** P1.
- **Critérios de verificação:** suíte verde e coerente; testes críticos presentes; CI bloqueia regressão; Dusk e scans executam.
- **Testes sugeridos:** os próprios testes; revisão de mutação; testes de autorização/exclusão/idempotência.

### F-22 — Operação sem backup automatizado, restore testado, DR, monitoramento ou resposta a incidentes

- **Categoria:** Operação, resiliência, continuidade.
- **Severidade técnica:** N/A (operacional); equivalente a Alto.
- **Risco de negócio:** Alto.
- **Probabilidade:** Média/Alta.
- **Confiança:** Confirmado no repositório; ambiente pode ter controles externos não documentados.
- **Componentes/evidência:**
  - `docs/DEPLOY_PORTAINER.md:313-343` descreve backup/restore manual, sem automação, criptografia, retenção ou teste.
  - `docker-compose.prod.yml:109-114` healthcheck apenas `curl /up`; `bootstrap/app.php:15` define `/up`; não verifica banco, Replicado, SMTP ou fila.
  - Não há configuração de APM, alertas, Sentry, Pulse, Telescope, runbooks, on-call, status page ou plano de incidentes no repositório.
  - `docker/production/entrypoint.sh:103-108` roda migrations automaticamente no start; sem backup/rollback.
  - `storage/app/app_key` é persistido no volume; se o volume for perdido, a chave muda (`entrypoint.sh:55-82`).
- **Descrição:** O sistema depende de banco externo e Replicado, mas não tem backup automático, restore testado, RTO/RPO, monitoramento de saúde real, alertas ou resposta a incidentes. O healthcheck não detecta banco/Replicado fora. A perda do volume de storage pode invalidar sessões/dados criptografados.
- **Cenário de falha:** corrupção do banco; volume perdido; Replicado indisponível; worker parado; ninguém percebe; restore manual falha; perda de histórico.
- **Pré-condições:** operação em produção.
- **Impacto técnico:** perda de dados; indisponibilidade prolongada; detecção tardia.
- **Impacto de negócio:** interrupção do serviço, perda financeira/reputacional, obrigações de continuidade.
- **Controles existentes:** healthcheck básico; restart automático; migrations automáticas; logs.
- **Controles ausentes:** backup automatizado/criptografado/offsite; restore drill; RTO/RPO; monitoramento de dependências; alertas; runbooks; on-call; gestão de incidentes.
- **Remediação:** definir RTO/RPO; automatizar backup criptografado e retenção; testar restore periodicamente; monitorar banco/Replicado/SMTP/fila; alertas; runbooks; on-call; status page; proteger APP_KEY; documentar.
- **Mitigação temporária:** backup manual imediato; testar restore; monitorar manualmente; congelar migrations.
- **Esforço/dependências:** M/L; depende de infra.
- **Prioridade:** P1.
- **Critérios de verificação:** restore realizado em ambiente limpo; RTO/RPO medidos; alerta dispara em falha de DB/Replicado; runbook testado.
- **Testes sugeridos:** restore drill; chaos de dependência; alerta.

### F-23 — Privacidade e conformidade: base legal, retenção, direitos e terceiros não demonstrados

- **Categoria:** Privacidade, LGPD, conformidade.
- **Severidade técnica:** N/A (jurídico/regulatório); equivalente a Alto.
- **Risco de negócio:** Alto.
- **Probabilidade:** Alta (lacunas estruturais).
- **Confiança:** Confirmado para ausência de artefatos; conclusão legal necessita revisão.
- **Componentes/evidência:**
  - Dados pessoais em `pessoas` (NUSP, nome), `users` (nome, e-mail, codpes), `vinculos` (vínculo), `lancamentos` (consumo), `audits` (IP, user agent, URL, valores antigos/novos), `sessions`, logs e debug OAuth.
  - `Server.php:61-62` grava PII OAuth; `ReplicadoService.php:31,40,47,53` loga e-mail/NUSP; `storage/logs/laravel.log` existe localmente.
  - Não há aviso de privacidade, política de retenção, base legal, processo de direitos do titular, DPO ou DPIA no repositório.
  - `profile/delete-user-form` exclui a conta e, por cascata, o histórico; não há anonimização/retenção.
  - `resources/views/welcome.blade.php` e layouts carregam fontes de `fonts.bunny.net`; há links `http://www.usp.br`.
- **Descrição:** O sistema trata dados pessoais e de consumo sem demonstrar base legal, finalidade, minimização, retenção, segurança e direitos dos titulares. A exclusão de conta conflita com a necessidade de reter histórico financeiro/auditoria. O debug OAuth e os logs ampliam a exposição. Terceiros (fontes, NPM, e-mail) não têm avaliação documentada.
- **Cenário de falha:** titular solicita exclusão; o sistema apaga o histórico ou não consegue anonimizar; auditoria perde prova; vazamento de PII em log/debug; fiscalização questiona base legal.
- **Pré-condições:** operação com dados reais.
- **Impacto técnico:** exposição e retenção indevida.
- **Impacto de negócio:** sanções, dano reputacional, obrigação de notificação, litígios.
- **Controles existentes:** acesso restrito ao painel; auditoria; HTTPS no NPM; algumas minimizações (não exibir senha).
- **Controles ausentes:** inventário/classificação; base legal; aviso; retenção; DSR; DPO; DPIA; contratos de operador; anonimização; avaliação de terceiros; treinamento.
- **Remediação:** programa de privacidade com inventário, base legal, aviso, retenção, DSR, anonimização, DPIA, contratos e treinamento; remover PII de logs/debug; revisar terceiros; alinhar exclusão com retenção legal.
- **Mitigação temporária:** reduzir coleta; desligar debug; limpar logs; restringir acesso; documentar decisões.
- **Esforço/dependências:** M/L; depende de jurídico/DPO.
- **Prioridade:** P1.
- **Critérios de verificação:** inventário aprovado; aviso publicado; retenção configurada; DSR testado; debug/logs sem PII; contratos assinados.
- **Testes sugeridos:** DSR; retenção; anonimização; revisão de logs.

### F-24 — Documentação contraditória e manutenibilidade frágil

- **Categoria:** Documentação, manutenção, risco de decisão.
- **Severidade técnica:** Baixa/Média.
- **Risco de negócio:** Médio.
- **Probabilidade:** Alta.
- **Confiança:** Confirmado.
- **Componentes/evidência:**
  - `README.md:42` e `AGENTE.md:230-231` falam `ADM`/`OPR`; `RoleSeeder.php:34-35` cria `Admin`/`Operador`; `AdminPanelProvider.php:124` aceita `ADM|Admin`; policies usam `Admin`/`Operador`.
  - `docs/analise/04-perfis-e-permissoes.md` diz que Operador gerencia cotas; o painel atual nega Operador (`User::canAccessPanel`).
  - `docs/arquitetura/02-mapeamento-logica-arquitetura.md:24` afirma validação de tipo via `#[Rule]`; `salvarLancamento` só valida valor.
  - `docs/arquitetura/02:25` cita método `buscarPessoa` que não existe em `ReplicadoService`.
  - `docs/arquitetura/02:27` afirma exportação no Extrato; `ExtratoResource`/`ExtratosTable` não têm exportação.
  - `docs/analise/01-04` descrevem o sistema legado Java/JSF, não a implementação Laravel atual.
  - `docs/termo_abertura_projeto.md:1,72-74` descreve um "Laravel 12 USP Starter Kit" e exclui funcionalidades de negócio; `README.md:25` afirma que o CotaG "já está em produção"; `AGENTE.md:33` diz "fase de desenvolvimento ativo".
  - `User.php:27-30` referencia `Grupo` e `BelongsToMany` sem import/classe; `Mensagem` está vazio; `UserForm` oferece `email_verified_at`, mas o campo não está em `$fillable`.
  - `AdminPanelProvider.php:132` mantém `strictAuthorization()` comentado.
- **Descrição:** A documentação não é uma fonte confiável para operação, auditoria ou desenvolvimento. Papéis, fluxos, validações e funcionalidades descritas divergem do código. Há código órfão e promessas não implementadas, aumentando o risco de mudanças erradas e de decisões baseadas em premissas falsas.
- **Cenário de falha:** operador recebe role `ADM` conforme README e não consegue usar recursos; administrador espera exportar extrato e não consegue; desenvolvedor assume validação de tipo que não existe.
- **Pré-condições:** leitura da documentação; evolução do sistema.
- **Impacto técnico:** configuração errada, retrabalho, regressão.
- **Impacto de negócio:** decisões erradas, atraso, risco operacional.
- **Controles existentes:** documentação extensa; docblocks.
- **Controles ausentes:** fonte única de verdade; testes de documentação; revisão; marcação de legado; remoção de código morto.
- **Remediação:** consolidar documentação atual; remover/arquivar docs legados; alinhar papéis; documentar fluxos reais; adicionar revisão de docs no PR; remover código morto; habilitar `strictAuthorization` após revisar policies.
- **Mitigação temporária:** não usar docs antigos para operação; validar com código; comunicar papéis corretos.
- **Esforço/dependências:** M.
- **Prioridade:** P2/P3.
- **Critérios de verificação:** docs e código concordam; teste de papel; links/funcionalidades documentadas existem; código morto removido.
- **Testes sugeridos:** revisão de docs; teste de roles; lint de links.

### F-25 — PII de debug OAuth pode ser copiada para a imagem de produção

- **Categoria:** Privacidade, dados, cadeia de suprimentos, build.
- **Severidade técnica:** Alta (CVSS 7.5 aproximado; exposição de PII em artefato imutável).
- **Risco de negócio:** Alto.
- **Probabilidade:** Alta (basta haver arquivo em `storage/app/private` no momento do build).
- **Confiança:** Confirmado por inspeção das regras de `.dockerignore`; não foi executado `docker build` neste ambiente.
- **Componentes/evidência:**
  - `.dockerignore:40-49` ignora `storage/app/*`, mas re-inclui `storage/app/private/` e `storage/app/public/`.
  - `docker/production/Dockerfile:81` executa `COPY . .` no builder e `:166` copia `/build` para a imagem final.
  - Existe ao menos um JSON de debug OAuth com PII em `storage/app/private/debug/oauth/` (valores e nome do arquivo não reproduzidos).
  - O runtime usa bind mount `./storage:/var/www/html/storage` (`docker-compose.prod.yml:104-105`), que mascara o diretório, mas não remove o conteúdo das camadas já publicadas.
- **Descrição:** Arquivos de PII no storage privado são parte do contexto de build. O `.dockerignore` exclui `.env` e logs, mas a negociação `!storage/app/private/` re-inclui o diretório; `COPY . .` leva o conteúdo para a imagem. A imagem pode ser enviada a registry/backup/host e o arquivo permanece nas camadas mesmo que o bind mount o oculte em runtime.
- **Cenário de abuso/falha:** desenvolvedor faz login OAuth local, gera JSON; o build de produção é feito na mesma árvore; a imagem publicada contém o JSON; qualquer acesso ao registry/host/backup da imagem obtém PII sem precisar do banco ou do storage de runtime.
- **Pré-condições:** arquivo PII presente no contexto de build; imagem construída e armazenada/distribuída.
- **Impacto técnico:** PII em artefato imutável; difícil purge (requer rebuild e limpeza de registry); pode sobreviver à rotação do storage.
- **Impacto de negócio:** não conformidade, vazamento de dados pessoais, dano reputacional, custo de resposta.
- **Controles existentes:** `.dockerignore` exclui `.env`, logs e a maior parte de `storage`; bind mount em runtime; storage fora de `public/`.
- **Controles ausentes:** exclusão explícita de `storage/app/private/debug` e de qualquer PII; verificação de camadas; build em contexto limpo/CI; SBOM/scan de segredos na imagem; retenção/purge.
- **Remediação:** remover `!storage/app/private/` ou excluir `storage/app/private/debug` e arquivos de PII; construir sempre em clone limpo/CI; adicionar etapa de secret/PII scan na imagem; usar BuildKit `--mount=type=secret` em vez de copiar segredos/PII; documentar purge e rebuild; validar camadas.
- **Mitigação temporária:** purgar `storage/app/private/debug/oauth` antes de qualquer build; adicionar `storage/app/private/debug` ao `.dockerignore`; reconstruir e republicar imagens; revogar/rotacionar qualquer segredo que possa estar em camada.
- **Esforço/dependências:** S/M; depende do pipeline de build/registry.
- **Prioridade:** P1.
- **Critérios de verificação:** `docker build` a partir de árvore com JSON de teste não inclui o arquivo (`docker history`/`docker run find`); scan de imagem não encontra PII; `.dockerignore` contém regra explícita; registry não tem imagem antiga com PII.
- **Testes sugeridos:** build com arquivo canário; inspeção de camadas; secret scan da imagem; rebuild/purge.

### F-26 — Containers de produção compartilham a rede `web` e ficam acessíveis sem passar pelo NPM

- **Categoria:** Infraestrutura, rede, configuração, autorização de borda.
- **Severidade técnica:** Média (CVSS 5.3–6.5 aproximado; acesso interno/lateral).
- **Risco de negócio:** Médio/Alto.
- **Probabilidade:** Média (depende de comprometimento de outro container na rede `web` ou de acesso à rede Docker).
- **Confiança:** Confirmado na configuração; impacto depende da topologia real.
- **Componentes/evidência:**
  - `docker-compose.prod.yml:104-108` coloca `app` nas redes `default` e `web`; `:126-130` coloca `worker` nas mesmas redes.
  - `:137-140` declara `web` como rede externa compartilhada com NPM/Portainer.
  - `docker/production/nginx.conf:26-29` escuta HTTP na porta 80 dentro do container.
  - `bootstrap/app.php:20-24` confia em qualquer proxy (`at: '*'`), inclusive chamadas diretas de outros containers.
- **Descrição:** O modelo pretende que só o NPM alcance `cotag-app:80`, mas qualquer container conectado à rede `web` pode fazê-lo diretamente. Isso contorna TLS/HSTS/headers/rate limiting do NPM e permite spoof de `X-Forwarded-*` (F-16). O worker não precisa estar na `web` para processar filas.
- **Cenário de abuso/falha:** container comprometido na rede `web` (outro stack, NPM, ferramenta) acessa `http://cotag-app:80` sem TLS, testa credenciais/endpoints e spoofa headers; ou um operador de infraestrutura acessa o app por HTTP interno, expondo sessão.
- **Pré-condições:** acesso à rede Docker `web`; app escutando em 80; trust proxy amplo.
- **Impacto técnico:** bypass de controles de borda; exposição de tráfego; spoof de IP/host; movimento lateral.
- **Impacto de negócio:** incidente de segurança, vazamento de sessão/dados, dificuldade de atribuição.
- **Controles existentes:** sem portas publicadas no host; NPM faz TLS; rede Docker isolada do host; healthcheck.
- **Controles ausentes:** rede interna dedicada app↔NPM; remoção do worker da `web`; network policy/firewall; trusted proxies restritos ao IP do NPM; mTLS; monitoramento de acesso direto.
- **Remediação:** criar rede interna só para NPM↔app; conectar o worker apenas à rede de banco/fila; não expor o app a redes de outros stacks; restringir `trustProxies` ao IP/CIDR do NPM; exigir TLS ou mTLS no trecho interno; monitorar conexões diretas.
- **Mitigação temporária:** remover `worker` da rede `web`; restringir quem entra na `web`; bloquear acesso direto por firewall; revisar proxies; monitorar logs.
- **Esforço/dependências:** S/M; depende da topologia Docker/NPM.
- **Prioridade:** P2.
- **Critérios de verificação:** nenhum container fora do NPM alcança `cotag-app:80`; worker não está na `web`; XFF de cliente direto é ignorado; tráfego interno validado.
- **Testes sugeridos:** teste de conectividade a partir de container não autorizado; teste de spoof; revisão de redes; inspeção de proxy.

---

## 8. Vulnerabilidades técnicas (visão consolidada)

| Área | Achados | Situação |
|---|---|---|
| Autenticação | F-01, F-03, F-15, F-16, F-17, F-18 | Login local sem MFA; canal SSO paralelo; identidade não única; open redirect |
| Autorização | F-03, F-13, F-14, S-03 | RBAC paralelo; auditoria de privilégios ausente; sem segregação |
| Sessão/cookies | F-16, S-10 | `Secure` não garantido; proxy amplo; headers incompletos |
| Injeção | Nenhum achado confirmado | Uso de bindings/Eloquent; não foi encontrada concatenação de entrada em SQL |
| XSS/CSRF | Nenhum achado crítico confirmado | Escaping/CSRF do framework; CSP ausente |
| Criptografia | F-05, F-16 | Replicado sem TLS/validação; cookie sem Secure |
| Segredos/configuração | F-01, F-04, F-18, F-25, S-04 | Credenciais padrão; debug OAuth; env de exemplo; artefatos locais; PII em camada de imagem |
| Exposição de arquivos | F-19, F-04, F-25, S-04 | Nginx/PHP; debug/storage/logs; PII copiada no build |
| Upload/SSRF/XXE/deserialização | N/A/parcial | Não há upload, fetch de URL ou XML exposto; verificar dependências |
| Rate limiting/DoS | F-11, F-17, S-02 | Busca Replicado e registro sem limite; pré-loads |
| Dependências/containers | F-20, F-25 | Build não reprodutível; EOL; sem scans; PII na imagem |
| CI/CD | F-20, F-21, F-25 | Sem scans; actions não pinadas; build a partir de árvore com PII |
| Rede/segmentação | F-26 | App e worker na rede `web`; acesso direto sem NPM |
| Logs/auditoria | F-04, F-13, F-23 | PII; lacunas; sem imutabilidade |
| Banco/schema | F-02, F-07, F-09, F-12, S-01 | Cascatas; sem unique/check/índices |

### Achados secundários

| ID | Achado | Evidência | Sev. técnica | Risco | Confiança | Prioridade | Remediação/verificação |
|---|---|---|---|---|---|---|---|
| S-01 | Schema sem unique/check/índices: `cotas.tipo_vinculo`, `cota_especiais.codigo_pessoa`, `users.codpes`; sem check de valor/tipo; sem índice composto para `(codigo_pessoa,data,tipo_lancamento)`; sem índice em `pessoas.nome_pessoa`. | migrations `cotas:14-18`, `cota_especiais:14-23`, `users update:17-20`, `lancamentos:16-18`; queries `CotaService:87`, `ManageLancamentos:153-157`. | Média | Médio | Confirmado | P2 | Criar constraints/índices após limpeza; validar duplicidades; medir consultas. Verificar: inserts duplicados falham; plano de execução usa índice. |
| S-02 | Performance/escala: `ExtratoTable`/`AuditResource` pré-carregam todos os usuários/pessoas; `NavigationCardsWidget` faz `count()` de todas as tabelas; busca local sem paginação; busca Replicado sem limite. | `ExtratosTable`, `AuditResource`, `NavigationCardsWidget`, `ManageLancamentos:153-158`. | Média | Médio | Confirmado | P2 | Paginação, limites, lazy loading, cache e índices; verificar latência com volume. |
| S-03 | Incoerências de autorização: `CotaPolicy::deleteAny`/`CotaEspecialPolicy::deleteAny`/`LancamentoPolicy::deleteAny` passam `'Operador'` como segundo argumento de `hasRole` (guard), não como role; `strictAuthorization()` comentado; policies dão CRUD a Operador em recursos que o painel nega. | `CotaPolicy:69-72`, `CotaEspecialPolicy:69-72`, `LancamentoPolicy:69-72`, `AdminPanelProvider:132`. | Média | Médio | Provável | P2 | Corrigir assinatura/guard; habilitar strict authorization após revisar policies; alinhar papéis. Verificar: bulk delete funciona apenas para Admin; teste de policy. |
| S-04 | Segredos/artefatos locais: `.env` não versionado presente com permissão 664 (legível por grupo/outros), contendo chaves/credenciais (valores redigidos); `storage/app/private/debug/oauth` com PII; `storage/logs/laravel.log` com dados locais; arquivos de ambiente de teste versionados contêm `APP_KEY` válida (valores redigidos). | `stat` do `.env`/storage; `git ls-files .env.testing .env.dusk.ci .env.dusk.local`; `.env.testing:7`, `.env.dusk.ci:7`. | Média | Médio | Confirmado | P2 | Restringir permissões; remover artefatos; rotacionar chaves de teste se houver risco de reuso; secret scanning. Verificar: `.env` 600; storage sem PII; histórico sem segredo. |
| S-05 | Código órfão/quebrado: `Mensagem` vazio; `User::grupos()` referencia `Grupo`/`BelongsToMany` sem import/classe; `UserForm` oferece `email_verified_at` não fillable; `CotaEspecialsTable` usa `->actions()`/`->bulkActions()` (API antiga do Filament 4); `Extratos/Pages/CreateExtrato.php` e `EditExtrato.php` não estão em `getPages`; `RoleSeeder`/docs divergem. | `Mensagem.php`, `User.php:27-30`, `UserForm:30-31`, `CotaEspecialsTable:39-47`, `Extratos/Pages`, `RoleSeeder:34-35`. | Baixa | Baixo | Confirmado | P3 | Remover código morto; corrigir modelo/form/tabela; alinhar docs. Verificar: análise estática sem erro; funcionalidade documentada. |
| S-06 | Terceiros no frontend: fontes de `fonts.bunny.net` em todas as páginas, sem SRI/avaliação; links `http://www.usp.br`; `robots.txt` permite indexar `/admin`/`/login`. | layouts/welcome; `public/robots.txt`. | Baixa | Baixo | Confirmado | P3 | Auto-hospedar fontes ou avaliar terceiro; usar HTTPS; `noindex` em páginas sensíveis; política de terceiros. |
| S-07 | `APP_KEY` é gerada e persistida em `storage/app/app_key` se ausente; perda do volume muda a chave e invalida sessões/dados criptografados; não há alerta. | `entrypoint.sh:55-82`. | Baixa | Médio | Confirmado | P2 | Definir `APP_KEY` no secret manager; backup seguro; alerta; documentar. Verificar: restore mantém chave. |
| S-08 | Healthcheck `/up` só verifica boot da aplicação; não testa DB/Replicado/SMTP/fila; worker sem healthcheck. | `docker-compose.prod.yml:109-114`, `bootstrap/app.php:15`. | Baixa | Médio | Confirmado | P2 | Healthchecks de dependências/readiness; alertas; remover `/up` público se desnecessário. |
| S-09 | Não há API pública, webhooks, multi-tenancy ou app móvel; não há CSRF exception; `config/cors.php` ausente. | `routes/`, `config/`. | N/A | Informativo | Confirmado | — | N/A; revisar se API for adicionada. |
| S-10 | `SESSION_ENCRYPT=false`; dados de sessão no banco não são criptografados em repouso; `APP_KEY` protege cookies, mas não o storage de sessão. | `.env.example:38-40`, `docker-compose.prod.yml:47`. | Baixa | Baixo | Confirmado | P3 | Avaliar criptografia de sessão/dados em repouso; restringir DB; retenção. |

---

## 9. Falhas de lógica de negócio e regras

1. **Saldo não é um registro contábil:** é recalculado com o estado atual; não há fechamento, snapshot, versão de regra ou imutabilidade (F-06).
2. **Cota especial não é única nem temporal:** múltiplos registros somam; não há vigência, motivo, autor ou aprovação (F-07).
3. **Crédito é arbitrário:** operador pode conceder qualquer valor, sem limite, origem ou aprovação (F-08).
4. **Cota regular vem de seeder de simulação:** valores de desenvolvimento podem virar política de produção (F-09).
5. **Vínculos não são atualizados:** pessoa que perde/ganha vínculo mantém cota antiga; falha do Replicado vira zero silencioso (F-10).
6. **Tipo de lançamento não é validado no ponto de escrita:** tipos inválidos criam transações fantasma; não há idempotência (F-12).
7. **Créditos expiram na virada do mês:** `calcularSaldo` só soma créditos do mês corrente; um crédito comprado para uso futuro desaparece. É uma decisão de negócio não documentada.
8. **Saldo negativo é permitido por design:** não há política de dívida, cobrança, bloqueio ou comunicação; o controle é manual. É uma decisão que precisa ser ratificada.
9. **Não há correção/estorno:** o extrato é somente leitura e não há fluxo de reversão; correções exigem edição direta no banco ou novo lançamento, sem trilha de aprovação.
10. **Não há fechamento de mês nem relatório imutável:** relatórios passados não são reproduzíveis (F-06).
11. **Não há regra para pessoa sem vínculo:** cota zero e permissão de débito; a pessoa pode consumir e ficar negativa sem elegibilidade (F-10).
12. **Não há escopo de unidade:** qualquer pessoa da USP é elegível; não há filtro por unidade/segmento, apesar de `REPLICADO_CODUNDCLG` existir (decisão não documentada).
13. **Não há limite de crédito por período/pessoa:** não há teto, saldo máximo ou alerta (F-08).
14. **Concorrência/idempotência:** duas requisições simultâneas podem duplicar lançamentos; não há chave de idempotência nem lock (F-12).
15. **Contradições documentais:** papéis, validação, método `buscarPessoa` e exportação não correspondem ao código (F-24).

---

## 10. Dados, privacidade e conformidade

### 10.1 Classificação dos dados

| Dado | Onde | Classificação | Observação |
|---|---|---|---|
| NUSP, nome, e-mail, telefone | `pessoas`, `users`, debug OAuth, logs | Pessoal/confidencial | Identificador institucional; telefone no debug |
| Vínculo (docente/aluno/servidor) | `vinculos`, debug OAuth | Pessoal/sensível por contexto | Pode revelar condição funcional/estudantil |
| Consumo de impressão/cópias | `lancamentos` | Pessoal/financeiro | Histórico de uso; pode ser sensível |
| Operador que registrou | `lancamentos.usuario_id` | Pessoal/interno | Responsabilidade |
| IP, user agent, URL | `audits`, logs, sessions | Pessoal/técnico | Retenção indefinida |
| Credenciais/chaves | `.env`, storage, CI | Segredo | Valores redigidos |
| Senhas | `users.password` | Segredo | Hash; não auditar |

### 10.2 Exposição e retenção

- **Debug OAuth:** PII em `storage/app/private/debug/oauth` por padrão; sem retenção (F-04).
- **Logs:** `ReplicadoService` registra e-mail/NUSP em `warning/info`; `storage/logs/laravel.log` contém dados locais; `LOG_DAILY_DAYS=14` por default, mas não há política formal (F-23).
- **Coleta por busca/validação:** a busca de pessoa grava/atualiza `pessoas` e `vinculos` no banco local (`ManageLancamentos.php:195-215`) e a validação do formulário de cota especial importa a pessoa antes de a cota ser salva (`CotaEspecialForm.php:35-47`); isso coleta PII de quem nunca teve lançamento.
- **Imagem:** o conteúdo de `storage/app/private` pode ser copiado para a imagem de produção (F-25), expondo PII em registry/camadas.
- **Auditoria:** `audits` guarda valores antigos/novos, IP, user agent, URL; sem retenção ou anonimização (F-13).
- **Backups:** documentação sugere dump SQL e tar de storage sem criptografia/retenção (F-22).
- **Exclusão:** exclusão de conta/pessoa remove lançamentos por cascata, conflitando com retenção e auditoria (F-02).
- **Terceiros:** fontes externas recebem IP/origem; NPM/SMTP/Replicado não têm avaliação documentada (F-23).

### 10.3 Conformidade

- **LGPD:** não há no repositório base legal, finalidade, aviso, retenção, DPO, DPIA, processo de direitos, contratos de operador ou registro de tratamento. **Necessita revisão jurídica.**
- **Contratos institucionais:** uso do Replicado/Senha Única e retenção de dados de consumo precisam estar cobertos por acordo/base legal. **Necessita verificação.**
- **Licenças:** dependências GPL em projeto MIT; distribuição pode exigir cumprimento de obrigações GPL. **Necessita revisão jurídica** (F-20).
- **Notificação de incidentes:** não há plano; obrigações legais/contratuais não documentadas.

---

## 11. Acesso, identidade e segregação de funções

### 11.1 Matriz observada

| Ator | Acesso | Observação |
|---|---|---|
| `usp_user` / `external_user` | Perfil, dashboard; não `/lancamento` | Registro público; papéis atribuídos no cadastro |
| `Operador` | `/lancamento` via `operar-sistema`; não painel | Políticas de cota/lançamento dão CRUD ao Operador, mas o painel nega |
| `Admin` | Painel, usuários, papéis, permissões, cotas, cotas especiais, extrato, auditoria, `/lancamento` | Concentra funções; sem maker-checker |
| `ADM` | Painel (canAccessPanel) | Policies não reconhecem `ADM`; incoerência |
| `OPR` | Sem painel | Tests/docs divergem |
| Admin do pacote Senha Única | `/loginas`, `/senhaunica-users` | Fora do RBAC/auditoria; depende de `SENHAUNICA_ADMINS`/gate |
| Visitante | `/`, `/login`, `/register`, `/forgot-password`, `/reset-password`, `/up` | Registro aberto |

### 11.2 Problemas

- **Dois sistemas de autorização:** Spatie `web` (Admin/Operador) e guard `senhaunica` (admin/boss/manager/user + vínculos). Não há mapeamento documentado (F-03).
- **Concentração de poder:** Admin configura cotas, credita, opera, gerencia usuários e audita (F-14).
- **Sem proteção do último admin:** autoedição, bulk delete e force delete (F-14).
- **Identidade não única:** `codpes` sem unique; múltiplas contas; SSO por `codpes` (F-15).
- **Sem MFA:** login local para todos; admin-set passwords sem política (F-16).
- **Sem revisão de acessos/offboarding:** não há processo no repositório; contas podem permanecer ativas.
- **Contas compartilhadas:** não foi possível verificar; a ausência de MFA e a possibilidade de login local aumentam o risco. **Necessita verificação organizacional.**
- **Acesso de fornecedor/emergência:** não documentado; **necessita verificação**.

---

## 12. Operação, resiliência e continuidade

| Tema | Situação | Risco |
|---|---|---|
| Backup | Manual, sem automação/criptografia/retenção/teste | Perda de dados; restore não confiável |
| Restore/DR | Sem RTO/RPO, sem drill, sem plano | Indisponibilidade prolongada |
| Migrations | Automáticas no start; sem backup/rollback | Falha pode travar deploy |
| APP_KEY | Gerada/persistida em storage se ausente; perda muda a chave | Sessões/dados criptografados inválidos |
| Healthcheck | Apenas `/up`; não verifica DB/Replicado/SMTP/fila | Falha silenciosa |
| Monitoramento | Sem APM/alertas/runbooks/on-call | Detecção e resposta tardias |
| Capacidade | Busca Replicado sem limite; queries sem índice; pré-loads | Saturação/latência |
| Dependências | Replicado, Senha Única, DB, NPM, SMTP | Falha em cascata; sem circuit breaker |
| Rede/segmentação | App e worker na rede externa `web`; app escuta HTTP na 80 | Acesso direto sem NPM; spoof de headers (F-26) |
| Ambientes | Dev/prod separados por compose; sem paridade de secrets | Configuração divergente |
| Patch management | Sem Dependabot/Renovate/SCA; PHP 8.2 próximo EOL | Vulnerabilidades não corrigidas |
| Acesso privilegiado | Admin concentrado; sem break-glass/revisão | Insider/abuso |
| Segregação de ambientes | `.env`/storage locais; `.dockerignore` ajuda | Vazamento local; dados de teste em produção não verificados |
| Retenção/descarte | Sem política; exclusão em cascata | Não conformidade/perda de prova |

---

## 13. Cadeia de suprimentos e dependências

### 13.1 Inventário resumido

- **PHP:** Laravel 12.33, Filament 4.1, Livewire 3.6.4, Symfony 7.3.4, Guzzle 7.10, `spatie/laravel-permission` 6.17, `owen-it/laravel-auditing` 14.0, `uspdev/replicado` 1.25.4, `uspdev/senhaunica-socialite` 7.3.19.
- **Node:** Vite 6.3.6, Tailwind 4.1.14, axios 1.8.4 (dev; empacotado no bundle), laravel-vite-plugin 1.2.0.
- **Imagens:** `php:8.2-cli`, `php:8.2-fpm`, `ubuntu:24.04` (dev), `mysql:8.0` (teste local), `selenium/standalone-chrome` (dev).
- **Licenças:** projeto MIT; dependências `uspdev` GPL-2.0-or-later/GPL-3.0-or-later; demais majoritariamente MIT/BSD.
- **CVEs:** não verificadas por SCA; **necessita verificação** com `composer audit`/`npm audit`/scanner de imagem.

### 13.2 Riscos

- Build não reprodutível (`latest`, `apt-get upgrade`, actions por tag).
- Instaladores baixados sem checksum/assinatura.
- Sem SBOM/proveniência/assinatura de imagem.
- PII de `storage/app/private` pode ser copiada para camadas da imagem (F-25).
- PHP 8.2 em fim de suporte de segurança (2026-12-31) e Node 20 no CI em EOL (abril/2026); planejar upgrade.
- Dependências GPL em projeto MIT; revisão jurídica necessária.
- Sem Dependabot/Renovate/política de patch.
- `composer.lock`/`package-lock` são positivos; manter e revisar.

---

## 14. Documentação, testes e manutenibilidade como risco

- **Contradições:** papéis `ADM`/`OPR` vs `Admin`/`Operador`; operador gerencia cotas nos docs, mas não no código; `buscarPessoa` inexistente; exportação inexistente; validação de tipo inexistente (F-24).
- **Documentação legada:** `docs/analise/01-04` descrevem Java/JSF, não o Laravel; podem induzir a decisões erradas.
- **Testes:** há testes que falham por método/campo inexistente, expectativa de crédito errada, redirecionamento errado e role `ADM` incompatível com policies (F-21).
- **Cobertura:** não há evidência de cobertura mínima; áreas críticas (autorização, exclusão, idempotência, auditoria, privacidade) não têm testes.
- **CI:** Pint/Larastan/PHPUnit, mas sem Dusk, SCA, secret/container/IaC scan; actions não pinadas.
- **Código órfão/dívida:** `Mensagem` vazio, `User::grupos()` quebrado, `email_verified_at` não fillable, `strictAuthorization` comentado, políticas que concedem Operador em recursos inacessíveis, `deleteAny` com assinatura incorreta.
- **Conhecimento tácito:** regras de cota, crédito, fechamento e retenção não estão formalizadas; dependem de pessoas.

---

## 15. Riscos organizacionais, humanos e de terceiros

- **Concentração de conhecimento:** regras de negócio e deploy em poucas pessoas; não há runbooks/ADRs atualizados.
- **Conflito de interesse/segregação:** Admin pode operar e auditar; não há aprovação independente para créditos/cotas.
- **Insider threat:** operador pode creditar e excluir a própria conta; admin pode alterar papéis e auditoria via banco.
- **Contas compartilhadas/credenciais padrão:** risco de senha padrão e login local; não foi possível verificar contas compartilhadas.
- **Terceiros:** Replicado/Senha Única/NPM/SMTP/fontes sem avaliação de SLA, segurança, privacidade e continuidade no repositório. **Necessita verificação contratual.**
- **Fornecedor de deploy:** Portainer/NPM; configuração e acesso não auditados.
- **Treinamento:** não há evidência de treinamento de operadores/admin em segurança, privacidade ou fraude.
- **Offboarding:** não há processo documentado para revogar acessos; contas podem permanecer.
- **Acesso de emergência:** não documentado; **necessita verificação**.

---

## 16. Roadmap de remediação

### P0 — Imediato (0–7 dias)

| Item | Achados | Esforço | Dependências | Critério de sucesso |
|---|---|---|---|---|
| Remover/tornar inócuos seeders de admin/teste; rotacionar credenciais | F-01 | S | Deploy | Nenhuma credencial padrão ativa |
| Bloquear exclusão em cascata; desativar autoexclusão/bulk delete | F-02, F-14 | M | Modelo de dados | Histórico preservado |
| Desabilitar `/loginas`/`/senhaunica-users` ou integrar ao RBAC+auditoria | F-03 | M | Produto/identidade | Sem canal paralelo |
| Definir fechamento mensal/snapshot imutável | F-06 | M | Contábil/produto | Saldo passado reproduzível |
| Política de crédito (limite, motivo, aprovação) | F-08 | M | Financeiro | Crédito controlado |
| Desligar `SENHAUNICA_DEBUG` e purgar PII | F-04 | S | Infra | Sem PII em storage |
| Exigir TLS/validação no Replicado | F-05 | M | Infra USP | Tráfego cifrado |
| `SESSION_SECURE_COOKIE=true`/HSTS/HTTPS | F-16 | S | NPM | Cookie seguro |
| Proteger último admin/autoedmissão | F-14 | S | — | Sem lockout |
| Revisar cotas especiais/duplicidades e cotas de produção | F-07, F-09 | S/M | IME | Política única |

### P1 — Curto prazo (7–30 dias)

- Auditoria de papéis/impersonação/CLI/imports e trilha imutável (F-13).
- Unicidade de `codpes`, vinculação SSO e reauth para troca de e-mail (F-15).
- Sincronização/TTL de vínculos e fail-closed do Replicado (F-10).
- Limite/escape/throttle na busca do Replicado (F-11).
- Validação de tipo/idempotência (F-12).
- MFA para Admin/Operador e política de senha para contas criadas por admin (F-16).
- Backup automatizado/criptografado, restore drill, RTO/RPO (F-22).
- Monitoramento/alertas/runbooks/IR (F-22).
- SCA/secret/container/IaC scan, SBOM, pinagem, upgrade PHP/Node, revisão GPL (F-20).
- Purgar PII e excluir `storage/app/private` do contexto de build; scan de imagem (F-25).
- Corrigir testes e adicionar testes críticos/Dusk (F-21).
- Programa de privacidade/LGPD (F-23).
- Corrigir defaults de ambiente e SMTP (F-18).

### P2 — Médio prazo (30–90 dias)

- Segregação de funções e workflows maker-checker (F-14).
- Open redirect/throttle de registro (F-17).
- Nginx/PHP-FPM hardening (F-19).
- Segmentar a rede app↔NPM e remover o worker da rede `web` (F-26).
- Índices/constraints, unique de cotas/cota especial, checks (F-07, F-09, F-12, S-01).
- Limpeza de código órfão, `strictAuthorization`, alinhamento de policies (F-24, S-03).
- Performance: paginação, pré-load controlado, cache (S-02).
- Documentação única e remoção de docs legados (F-24).
- Restringir permissões de `.env`/storage, secret scanning e rotação de chaves de teste (S-04).
- Healthchecks de dependências e remoção de `/up` público se desnecessário (S-08, S-07).
- Avaliar criptografia de sessão/dados em repouso (S-10).

### P3 — Estrutural (90+ dias)

- Modelo contábil com fechamento, versionamento e retenção formal.
- Separação de identidade/transação e anonimização.
- Programa de privacidade contínuo, DPIA, contratos e treinamento.
- Observabilidade madura (APM, SLOs, alertas, on-call, DR testado).
- Revisão de arquitetura de autenticação (SSO único, MFA, sem login local privilegiado) e de autorização (RBAC único, sem canal paralelo).
- Substituição/upgrade de dependências EOL e revisão de licenças.

### Quick wins

- Desligar `SENHAUNICA_DEBUG`; `SESSION_SECURE_COOKIE=true`; HSTS; restringir proxy; remover bulk delete; bloquear `/storage/*.php`; unique de `codpes`/cota especial; limitar busca Replicado; corrigir validação de tipo; adicionar `try_files`; remover credenciais padrão.

### Responsáveis sugeridos

- **Tech lead/segurança:** F-01, F-03, F-06, F-13, F-14, F-16, F-20, F-21.
- **DBA/backend:** F-02, F-07, F-09, F-12, S-01, S-02.
- **Infra/DevOps:** F-04, F-05, F-16, F-18, F-19, F-20, F-22, S-04, S-07, S-08.
- **Produto/negócio:** F-06, F-07, F-08, F-09, F-15, decisões de retenção/crédito.
- **DPO/jurídico:** F-20, F-23, contratos, LGPD, GPL.
- **QA:** F-21, plano de verificação.

---

## 17. Plano de verificação

### 17.1 Critérios de aceitação por tema

| Tema | Critério reproduzível | Evidência esperada |
|---|---|---|
| Bootstrap de admin | `RUN_SEEDERS=true` em ambiente limpo não cria conta conhecida; pré-registro + seed não concede Admin | Log de seed, teste automatizado, print |
| Exclusão | Usuário/pessoa com lançamentos não pode ser excluído fisicamente; desativação preserva histórico | Teste de integração, contagem de linhas, extrato |
| Canal Senha Única | Rotas não aprovadas retornam 404/403; impersonação e alteração de papel geram auditoria | `route:list`, teste de 403, evento de auditoria |
| Fechamento | Alterar cota após fechamento não muda saldo fechado; relatório reproduzível | Snapshot, hash, teste de regressão |
| Cota especial | Duplicata ativa é rejeitada; expirada não conta; motivo/autor registrados | Teste de unique/vigência, auditoria |
| Crédito | Limite e aprovação aplicados; relatório reconcilia origem | Teste de limite/aprovação, relatório |
| Replicado | TLS validado; falha parcial bloqueia ou alerta; vínculo atualizado por TTL | Captura de tráfego, teste de falha, log |
| Busca | Limite N, wildcard escapado, throttle 429, sem N+1 | Teste de carga/limite |
| Lançamento | Tipo inválido rejeitado; retry idempotente; saldo/extrato concordam | Teste de replay/tipo |
| Auditoria | Papéis/impersonação/CLI/imports auditados; trilha imutável | Consulta de auditoria, teste de tamper |
| Sessão | Cookie `Secure`/`HttpOnly`/`SameSite`; HSTS; proxy restrito | Inspeção de headers, teste de spoof |
| Privacidade | Debug/logs sem PII; retenção; DSR | Varredura, teste de DSR, revisão |
| Backup/DR | Restore em ambiente limpo dentro do RTO; alertas de falha | Relatório de drill, métricas |
| Supply chain | Build reprodutível; CI bloqueia CVE crítica; SBOM/licenças | Pipeline, SBOM, relatório |
| Imagem | Build não contém PII/segredos; scan de camada limpo | Inspeção de camadas, secret scan |
| Rede | Apenas NPM alcança o app; worker fora da rede `web` | Teste de conectividade, firewall |

### 17.2 Testes e ferramentas sugeridos

- **SAST:** Larastan/Semgrep/CodeQL; revisar policies e fluxos de autorização.
- **DAST:** OWASP ZAP contra ambiente de teste, com autenticação de Admin/Operador; foco em IDOR/BOLA, open redirect, CSRF, XSS, headers.
- **SCA:** `composer audit`, `npm audit`, Dependabot/Renovate; scanner de imagem (Trivy/Grype).
- **Secret scanning:** gitleaks/trufflehog no repo e histórico.
- **IaC/container:** Checkov/Trivy config; validar Nginx/PHP-FPM/compose.
- **Testes de autorização:** matriz de ator × recurso × ação; incluir `/loginas`, `/senhaunica-users`, Filament, Livewire.
- **Testes de negócio:** fechamento, cota especial, crédito, idempotência, concorrência, estorno.
- **Testes de privacidade:** retenção, DSR, anonimização, logs/debug.
- **Teste de restore/DR:** banco + storage + APP_KEY; medir RTO/RPO.
- **Pentest:** externo, com escopo aprovado, após correções P0/P1.

### 17.3 Evidências de fechamento

- Relatório de re-teste por achado, com data, ambiente, versão e resultado.
- Prints/logs de testes, consultas de auditoria, diffs de configuração.
- SBOM, relatório SCA, imagem assinada/digest.
- Ata de aprovação de risco residual e decisões de produto.
- Atualização da documentação e do runbook.

---

## 18. Riscos residuais e riscos aceitos

| Risco | Por que pode permanecer | Quem aceita (sugerido) | Monitoramento |
|---|---|---|---|
| Saldo negativo permitido | Decisão de negócio | Product Owner/Financeiro | Relatório de negativos, cobrança |
| Créditos expiram no mês | Regra de negócio | Product Owner | Comunicação, relatório de créditos |
| Login local para usuários comuns | Conveniência/legado | Segurança/Produto | Revisão periódica, MFA para privilegiados |
| Replicado sem TLS (se legado não suportar) | Restrição de infraestrutura | Infra/Segurança | Segmentação, monitoramento, exceção formal |
| Dependências GPL | Decisão jurídica | Jurídico/Reitoria | Parecer, obrigações de distribuição |
| Retenção de PII em auditoria | Necessidade legal/auditoria | DPO/Jurídico | Retenção definida, acesso restrito |
| o e-mail administrativo padrão (redigido) como identidade | Legado | Segurança | Remoção/renomeação, monitoramento |
| Dados históricos em storage | Backup/legado | Infra/DPO | Purge, criptografia, inventário |

> Nenhum risco deve ser considerado “aceito” sem registro formal, responsável nomeado, justificativa, prazo e monitoramento. “A definir” não é aceite.

---

## 19. Decisões necessárias e perguntas para o dono do produto

1. O registro público é intencional? Quem pode criar conta? Deve ser por convite/aprovação?
2. O login local por senha deve existir para Admin/Operador ou apenas para usuários comuns? A Senha Única exige MFA? O login local pode contorná-la?
3. `/loginas` e `/senhaunica-users` são necessários? Quem deve ser administrador do pacote? Como auditar impersonação?
4. Qual é a tabela oficial de cotas por vínculo, quem aprova, qual a vigência e como comunicar?
5. Cota especial substitui ou soma a cota regular? Pode ser menor? Qual a vigência, motivo e aprovação?
6. Créditos expiram no fim do mês? Podem ser comprados antecipadamente? Qual o limite, origem financeira e aprovação?
7. Saldo negativo é dívida? Há cobrança, bloqueio, comunicação ou perdão? Quem decide?
8. Qual é o fechamento mensal, o relatório oficial, o RTO e o RPO? Quem assina o fechamento?
9. Qual é a base legal, finalidade, retenção e processo de direitos para NUSP, vínculo, consumo, IP e logs? Quem é o DPO?
10. Exclusão de conta deve apagar o histórico ou anonimizar? Qual é a retenção legal/contratual?
11. O Replicado pode usar TLS validado? Se não, qual é a exceção formal e a compensação?
12. Quais são os SLAs/contratos com USP/NPM/SMTP/fontes? Há avaliação de fornecedor?
13. Dependências GPL são aceitáveis? O projeto será distribuído? Há obrigação de abrir código?
14. Quem são os administradores/operadores, quem aprova acessos e como é o offboarding?
15. Quais dados de teste são permitidos? Há PII em ambientes de teste? Como mascarar?
16. Qual é a política de patches e o prazo para corrigir CVE crítica?
17. Qual é o processo de incidente, notificação e comunicação? Quem decide?
18. O sistema deve ser multi-unidade? Há escopo por `REPLICADO_CODUNDCLG`?

---

## 20. Anexos

### 20.1 Inventário de rotas

| Rota | Método | Acesso | Observação |
|---|---|---|---|
| `/` | GET | Público | Welcome; fontes externas |
| `/register` | GET/POST (Volt) | Guest | Registro público |
| `/login/local` | GET/POST (Volt) | Guest | Senha local |
| `/forgot-password` | GET/POST (Volt) | Guest | Reset; mailer log por default |
| `/reset-password/{token}` | GET/POST (Volt) | Guest | Token na URL |
| `/verify-email` | GET/POST (Volt) | Auth | Reenvio sem throttle |
| `/verify-email/{id}/{hash}` | GET | Auth+signed+throttle | Verificação |
| `/confirm-password` | GET/POST (Volt) | Auth | Confirmação |
| `/dashboard` | GET | Auth+verified | Dashboard |
| `/lancamento` | GET | Auth+verified+`operar-sistema` | Operador |
| `/profile` | GET/PATCH/DELETE (Volt) | Auth | Autoexclusão |
| `/up` | GET | Público | Healthcheck simples |
| `/admin` e recursos | GET/POST | Filament+role ADM/Admin | Painel |
| `/login` | GET | Público | OAuth Senha Única; `redirect` aberto |
| `/callback` | GET | Público | Callback OAuth |
| `/logout` | POST | Auth | Pacote Senha Única |
| `/loginas` | GET/POST | Gate `admin` (senhaunica) | Impersonação |
| `/undologinas` | GET | Gate `user` | Retorno |
| `/senhaunica-users` e subrotas | GET/POST/PUT/DELETE | Gate `admin` (senhaunica) | Gestão de usuários do pacote |

### 20.2 Dependências principais e licenças

| Pacote | Versão | Licença | Observação |
|---|---|---|---|
| laravel/framework | 12.33.0 | MIT | Base |
| filament/filament | 4.1.0 | MIT | Painel |
| livewire/livewire | 3.6.4 | MIT | UI reativa |
| livewire/volt | 1.7.1 | MIT | Rotas/componentes |
| spatie/laravel-permission | 6.17.0 | MIT | RBAC |
| owen-it/laravel-auditing | 14.0.0 | MIT | Auditoria |
| uspdev/replicado | 1.25.4 | GPL-2.0-or-later | Revisão jurídica |
| uspdev/senhaunica-socialite | 7.3.19 | GPL-2.0-or-later | Revisão jurídica |
| uspdev/cache | 1.0.0 | GPL-3.0-or-later | Revisão jurídica |
| axios | 1.8.4 | MIT | Dev; bundle |
| vite | 6.3.6 | MIT | Build |

### 20.3 Configurações e segredos (redigidos)

| Item | Local | Situação | Ação |
|---|---|---|---|
| `.env` local | raiz (não versionado) | Presente; permissão 664; chaves/credenciais (valores redigidos) | Restringir 600; rotacionar se exposto |
| `.env.testing` | versionado | `APP_KEY` válida (valor redigido); credenciais de teste | Tratar como chave comprometida se reutilizada |
| `.env.dusk.ci` / `.env.dusk.local` | versionados | Mesma `APP_KEY` nos dois (valor redigido); credenciais de teste | Rotacionar/remover do versionamento |
| `storage/app/private/debug/oauth` | storage | JSON de OAuth com PII (valores redigidos); pode ser copiado no build (F-25) | Purgar; desligar debug; excluir do `.dockerignore` |
| `storage/logs/laravel.log` | storage | Log local com dados de aplicação | Restringir; política de retenção |
| `APP_KEY` em `storage/app/app_key` | storage | Gerada se ausente | Secret manager/backup seguro |
| `SENHAUNICA_KEY/SECRET` | env/Portainer | Não versionados em produção | Secret manager; rotação |
| `REPLICADO_PASSWORD` | env/Portainer | Não versionado em produção | Secret manager; rotação |
| `DB_PASSWORD` | env/Portainer | Default placeholder no compose (valor redigido) | Substituir; secret manager |

### 20.4 Integrações

| Integração | Direção | Dados | Risco |
|---|---|---|---|
| Replicado USP | Leitura | Nome, NUSP, e-mail, vínculo | TLS fraco; disponibilidade; PII |
| Senha Única | OAuth1 | Identidade, e-mail, telefone, vínculo | Debug PII; rotas administrativas; open redirect |
| MySQL/MariaDB | Leitura/escrita | Todo o domínio | Credenciais/TLS não verificados |
| NPM | TLS/entrada | HTTP headers | Trust proxy; HSTS |
| Rede Docker `web` | Entrada interna | HTTP app:80; headers | Acesso direto sem NPM; spoof (F-26) |
| SMTP/log | Saída | Tokens, PII | Token em log; entrega |
| Bunny Fonts | Saída (navegador) | IP/origem | Privacidade/terceiro |

### 20.5 Dados, papéis e jobs

- **Tabelas:** `users`, `pessoas`, `vinculos`, `cotas`, `cota_especiais`, `lancamentos`, `audits`, `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`, `sessions`, `cache`, `jobs`, `failed_jobs`, `notifications`, `password_reset_tokens`, `mensagens`.
- **Papéis observados:** `Admin`, `Operador`, `usp_user`, `external_user`, `ADM`, `OPR` (legado/testes), permissões `senhaunica` (`admin`, `boss`, `manager`, `user`, vínculos).
- **Jobs/filas:** `QUEUE_CONNECTION=database`; worker no compose; não há jobs de domínio definidos; não há monitoramento de falhas.
- **Órfãos:** `Mensagem` sem uso; `grupos()` sem classe; `email_verified_at` não fillable; `Extrato` sem exportação; `buscarPessoa` inexistente.

### 20.6 Testes inventariados

| Área | Situação | Observação |
|---|---|---|
| Auth/registro/login | Parcial | Testes esperam `/dashboard`, código redireciona `/lancamento` |
| Replicado | Quebrado | Método/campos inexistentes |
| CotaService | Contraditório | Teste de crédito diverge do código/README |
| Filament | Inconsistente | Role `ADM` vs policies `Admin`/`Operador` |
| Dusk | Fora do CI | Não executado no workflow |
| Segurança | Ausente | Sem testes de autorização, exclusão, idempotência, auditoria, privacidade |

### 20.7 Documentação contraditória (resumo)

| Documento | Afirma | Código |
|---|---|---|
| `README.md:42`, `AGENTE.md:230-231` | `ADM`/`OPR` | `RoleSeeder` cria `Admin`/`Operador` |
| `docs/analise/04` | Operador gerencia cotas | Painel nega Operador |
| `docs/arquitetura/02:24` | Tipo validado por `#[Rule]` | Só valor é validado no save |
| `docs/arquitetura/02:25` | `buscarPessoa` orquestra | Método não existe |
| `docs/arquitetura/02:27` | Extrato exporta | Sem exportação |
| `docs/analise/01-04` | Sistema Java/JSF | Implementação Laravel |
| `docs/termo_abertura_projeto.md:1,72-74` | Starter kit; exclui funcionalidades de negócio | CotaG em produção (`README.md:25`) |

### 20.8 Glossário

- **Cota base:** valor máximo entre as cotas dos vínculos da pessoa.
- **Cota especial:** exceção por pessoa; hoje somada e sem vigência.
- **Crédito:** lançamento tipo 0; adiciona saldo no mês.
- **Débito:** lançamento tipo 1; subtrai saldo no mês.
- **Fechamento:** consolidação imutável do saldo por pessoa/mês (não implementado).
- **RBAC:** controle de acesso baseado em papéis (Spatie/Filament).
- **Replicado:** base corporativa USP (SQL Server).
- **Senha Única:** OAuth institucional USP.
- **STRIDE/LINDDUN:** modelos de ameaça de segurança/privacidade.
- **P0–P3:** prioridade de remediação (P0 imediata; P3 estrutural).

### 20.9 Referências

- OWASP Top 10, OWASP ASVS, OWASP API Security Top 10.
- STRIDE (Microsoft) e LINDDUN (privacidade).
- CVSS v3.1 (FIRST).
- LGPD (Lei 13.709/2018) — revisão jurídica necessária.
- Documentação Laravel 12, Filament 4, Livewire 3, Spatie Permission, OwenIt Auditing.
- Documentação dos pacotes `uspdev/replicado` e `uspdev/senhaunica-socialite` no vendor.
- Arquivos e linhas citados ao longo do relatório.

---

## 21. Conclusão

O CotaG tem uma base funcional e controles corretos, mas o nível atual de assurance não é compatível com um sistema que controla consumo, crédito e histórico financeiro de uma comunidade institucional. Os riscos mais graves não são exóticos: são credenciais padrão, exclusão em cascata, um canal administrativo paralelo, ausência de fechamento contábil e PII em debug/log/imagem. Esses pontos devem ser tratados como P0 antes de qualquer expansão de uso.

A correção exige decisões de produto e de governança, não apenas código: definir a política de cotas/crédito, o modelo de identidade, a retenção de dados, a segregação de funções e o processo de auditoria. Sem essas decisões, correções técnicas serão frágeis.

Relatório concluído. Aguardando o próximo comando.

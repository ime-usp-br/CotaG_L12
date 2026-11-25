# CotaG - Sistema de Gestão de Cotas de Gráfica

**Versão:** 1.0.0
**Data:** 2026-01-15

[![Status da Build](https://github.com/ime-usp-br/CotaG/actions/workflows/laravel.yml/badge.svg)](https://github.com/ime-usp-br/CotaG/actions/workflows/laravel.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

## 1. Introdução

O **CotaG** é o sistema institucional de gestão de cotas de impressão e cópias para a comunidade acadêmica do Instituto de Matemática e Estatística da Universidade de São Paulo (IME-USP).

Desenvolvido para substituir o sistema legado (Java/JSF), o CotaG utiliza tecnologias modernas baseadas em **Laravel 12** e **FilamentPHP** para oferecer uma interface ágil, responsiva e integrada aos sistemas corporativos da USP.

**Objetivo:** Controlar e auditar o consumo de recursos da gráfica, garantindo que docentes, funcionários e alunos utilizem suas cotas de acordo com as políticas institucionais.

## 2. Público-Alvo

*   **Administradores do Sistema:** Equipe técnica responsável pela manutenção, configuração de regras de cotas e gestão de usuários.
*   **Operadores da Gráfica:** Funcionários que realizam os lançamentos diários de cópias e impressões.
*   **Comunidade IME-USP:** Usuários finais (Docentes, Alunos, Funcionários) que consomem os serviços.

## 3. Principais Funcionalidades

O sistema já está em produção com as seguintes capacidades:

*   **Gestão de Cotas:**
    *   **Cotas Regulares:** Definição automática baseada no vínculo da pessoa com a USP (ex: Docentes, Alunos de Graduação, Funcionários).
    *   **Cotas Especiais:** Atribuição de cotas excecionais para indivíduos específicos, sobrepondo a regra padrão.
*   **Controle de Lançamentos:**
    *   Registro rápido de **Débitos** (consumo) e **Créditos** (adição de saldo).
    *   Histórico completo de transações por usuário.
    *   Cálculo dinâmico de saldo: `Saldo = Cota Base + Créditos do Mês - Débitos do Mês`.
*   **Integração USP:**
    *   **Dados Corporativos (Replicado):** Busca automática de pessoas e vínculos diretamente da base de dados da USP.
    *   **Autenticação (Senha Única):** Login integrado via OAuth para administradores e operadores.
*   **Painéis de Acesso:**
    *   **Painel Administrativo (Filament):** Interface completa para gestão de usuários, logs, auditoria e configurações globais.
    *   **Painel do Operador:** Interface otimizada em Livewire para atendimento rápido no balcão da gráfica.
*   **Auditoria e Segurança:**
    *   Logs detalhados de todas as operações críticas.
    *   Controle de acesso baseado em papéis (RBAC): `ADM` (Admin) e `OPR` (Operador).

## 4. Stack Tecnológica

*   **Framework:** Laravel 12 (PHP 8.2+)
*   **Painel Admin:** FilamentPHP v4
*   **Frontend Operacional:** TALL Stack (Tailwind CSS, Alpine.js, Livewire 3)
*   **Banco de Dados:** MySQL / MariaDB
*   **Bibliotecas USP:**
    *   `uspdev/senhaunica-socialite`: Autenticação OAuth.
    *   `uspdev/replicado`: Integração com dados corporativos.
*   **Qualidade:** Laravel Pint, Larastan (PHPStan).
*   **Testes:** PHPUnit, Laravel Dusk.

## 5. Instalação

### 5.1. Pré-requisitos
*   Docker e Docker Compose v2 (recomendado); OU PHP 8.2+, Composer, Node.js e MySQL.
*   Para o ambiente Docker, um banco externo acessível por domínio (dev) e a rede externa `web` do NPM/Portainer.
*   O compose de deploy não sobe banco local: veja `docs/deploy/README.md`.

### 5.2. Instalação com Docker (stack única)

1.  **Clonar o repositório:**
    ```bash
    git clone https://github.com/ime-usp-br/CotaG_L12.git
    cd CotaG_L12
    ```

2.  **Configurar o ambiente local:**
    ```bash
    cp .env.example .env
    ```
    Edite o `.env` com o domínio do banco de dev, `APP_KEY`, credenciais USP e
    demais variáveis. O `.env` local é opcional e não é usado no Portainer.

3.  **Criar a rede externa (uma vez) e subir a stack:**
    ```bash
    docker network create web
    make up
    ```

4.  **Preparar a aplicação:**
    ```bash
    make migrate
    ```
    Em dev, `RUN_SEEDERS=true` faz o entrypoint rodar os seeders no boot. Use
    `make seed` apenas para reexecutar seeders manualmente (dev/homologação).

5.  **Validar:**
    ```bash
    make health
    make logs
    ```

> O `docker-compose.yml` é a stack única de dev, homologação e produção. Os
> arquivos antigos de Sail/produção foram arquivados em
> `docs/deploy/_legacy/` e não fazem parte do contrato.

### 5.3. Instalação Tradicional (Sem Docker)

1.  **Clonar o Repositório:**
    ```bash
    git clone https://github.com/ime-usp-br/CotaG.git
    cd CotaG
    ```

2.  **Instalar Dependências:**
    ```bash
    composer install
    npm install
    ```

3.  **Configurar Ambiente:**
    Sozinho ou copie o exemplo:
    ```bash
    cp .env.example .env
    php artisan key:generate
    ```
    Edite o `.env` com suas credenciais de Banco de Dados e USP.

4.  **Banco de Dados:**
    ```bash
    php artisan migrate --seed
    ```

5.  **Frontend:**
    ```bash
    npm run build
    ```

### 5.4. Deploy em Produção (Portainer + Nginx Proxy Manager)

O CotaG usa uma stack única para dev, homologação e produção:

- `docker-compose.yml` é o arquivo do stack no Portainer;
- o serviço `app` roda **Nginx + PHP-FPM** e não publica portas no host;
- o `worker` usa a mesma imagem, sem rede `web`, sem migrations e sem seeders;
- app e NPM compartilham a rede externa `web`, com alias `cotag-<env>-web`;
- o **Nginx Proxy Manager** faz o HTTPS e encaminha para
  `http://cotag-<env>-web:8080`;
- o banco MySQL/MariaDB/PostgreSQL é externo, acessado por domínio
  (`DB_HOST`), fora da rede Docker da aplicação.

Passos resumidos:

1. Solicite à infraestrutura o banco, usuário, permissões e DNS do ambiente.
2. No Portainer, crie a stack `cotag-<env>` apontando para o repositório com
   **Compose path** `docker-compose.yml`.
3. Copie as variáveis de `.env.example` para o campo de variáveis da stack e
   preencha os valores do ambiente (sem subir `.env` no servidor).
4. No NPM, crie um Proxy Host para o domínio do ambiente com forward para
   `cotag-<env>-web:8080` e **Websockets Support** habilitado.

O guia completo (variáveis, banco externo, NPM, migrations, backup, cutover e
troubleshooting) está em
[docs/deploy/README.md](./docs/deploy/README.md).

## 6. Configuração USP

Para funcionamento completo, configure as seguintes variáveis no `.env`:

**Senha Única (Autenticação):**
```env
SENHAUNICA_KEY=seu_client_id
SENHAUNICA_SECRET=seu_client_secret
SENHAUNICA_CALLBACK_ID=seu_callback_url
```

**Replicado (Dados Institucionais):**
```env
REPLICADO_HOST=host_replicado
REPLICADO_PORT=port
REPLICADO_DATABASE=db
REPLICADO_USERNAME=user
REPLICADO_PASSWORD=pass
REPLICADO_CODUNDCLG=45
```

## 7. Painel Administrativo

O CotaG utiliza o **Filament** para a área administrativa.

### 7.1. Acessando o Painel
Acesse `/admin` no seu navegador (ex: `http://localhost/admin`).

### 7.2. Criando o Primeiro Usuário Admin
Se você acabou de instalar o sistema, precisará criar um usuário com acesso administrativo.

1.  **Via Terminal (Interactive):**
    ```bash
    # Com Docker (stack única)
    docker compose exec app php artisan make:filament-user

    # Ou com PHP local
    php artisan make:filament-user
    ```
    Siga as instruções para definir nome, e-mail e senha.

2.  **Atribuindo Permissões (Se necessário):**
    O comando acima já cria um usuário apto a logar no Filament. Caso precise atribuir a role `ADM` explicitamente a um usuário existente:
    ```bash
    docker compose exec app php artisan tinker
    ```
    ```php
    $user = \App\Models\User::where('email', 'seu-email@usp.br')->first();
    $user->assignRole('ADM');
    ```

## 8. Ferramentas e Qualidade de Código

Este projeto inclui ferramentas para manter a qualidade e consistência do código:

*   **Laravel Pint:** Formatador de código automático (PSR-12).
    *   Para formatar: `vendor/bin/pint`
*   **Larastan (PHPStan):** Análise estática para encontrar erros.
    *   Para analisar: `vendor/bin/phpstan analyse`
*   **EditorConfig:** Padronização de configurações do editor (indentação, linhas, etc).

## 9. Testes

*   **Executando Testes PHPUnit (Unitários e Feature):**
    ```bash
    make test
    # ou, diretamente:
    docker compose -f docker-compose.test.yml run --rm test
    ```
    A stack de teste usa um MySQL efêmero em `tmpfs` e não toca no banco de
    dev, homologação ou produção.
*   **Executando Testes Dusk (Browser / End-to-End):**
    É necessário criar o arquivo `.env.dusk.local` e ter um Selenium acessível.
    ```bash
    php artisan dusk
    ```

## 10. Documentação

A documentação detalhada do projeto encontra-se na pasta `docs/` e na Wiki:

*   [Termo de Abertura do Projeto](./docs/termo_abertura_projeto.md)
*   [Guia de Desenvolvimento](./docs/guia_de_desenvolvimento.md)
*   [Padrões de Código e Boas Práticas](./docs/padroes_codigo_boas_praticas.md)
*   [Arquitetura e Lógica do Sistema](./docs/arquitetura/02-mapeamento-logica-arquitetura.md)
*   [Deploy (Portainer + NPM)](./docs/deploy/README.md)

## 11. Como Contribuir

1.  Identifique ou crie uma **Issue** no GitHub.
2.  Crie um **Branch** específico para a Issue (`feature/nova-funcionalidade` ou `fix/bug-fix`).
3.  Faça **Commits Atômicos** referenciando a Issue (`#<ID>`).
4.  Abra um **Pull Request (PR)** para o branch principal.
5.  Aguarde a revisão e aprovação.

## 12. Licença

Este projeto é licenciado sob a **Licença MIT**. Veja o arquivo [LICENSE](./LICENSE) para mais detalhes.

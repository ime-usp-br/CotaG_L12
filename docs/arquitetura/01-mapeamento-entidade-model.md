# Mapeamento de Entidades do Banco de Dados para Models Eloquent

## 1. Introdução

Este documento define a arquitetura da camada de dados para a nova versão do sistema CotaG, construída sobre o framework Laravel 12. O objetivo é documentar o mapeamento oficial das tabelas do banco de dados para Models Eloquent, refletindo a implementação final do projeto.

Esta documentação serve como a "fonte da verdade" para a estrutura de dados, consolidando as decisões de design tomadas durante o desenvolvimento, incluindo a adoção de pacotes como `spatie/laravel-permission` e `owen-it/laravel-auditing`.

## 2. Visão Geral do Esquema

O esquema da aplicação classifica as tabelas em três categorias:

1.  **Entidades de Domínio (Ativas):** Tabelas que representam o núcleo da lógica de negócio (`pessoas`, `lancamentos`, `cotas`, `cota_especiais`, `vinculos`). Estas possuem Models Eloquent correspondentes.

2.  **Funcionalidades de Framework (Pacotes):** Tabelas cuja funcionalidade é gerenciada por pacotes padrão do ecossistema Laravel:
    *   **Autenticação e Acesso:** `users`, `roles`, `permissions` (gerenciados por Laravel Breeze e Spatie Permission).
    *   **Auditoria:** `audits` (gerenciado por OwenIt Auditing), substituindo a antiga tabela `LOG`.

3.  **Entidades Descartadas (Legado):** Tabelas do sistema antigo que foram descontinuadas na nova arquitetura (`OU`, `GRUPO`, `MENSAGEM`, `PESSOA_LANCAMENTO`, `hibernate_sequence`, `REQUISICAO_SENHA`, `TOKEN`, `USUARIO_GRUPO`).

## 3. Mapeamento das Entidades

A tabela a seguir detalha o mapeamento oficial para a arquitetura Laravel/Filament.

| Tabela (Banco) | Model Laravel (Implementado) | Chaves e Atributos Críticos | Observações |
| :--- | :--- | :--- | :--- |
| **pessoas** | `App\Models\Pessoa` | **PK:** `codigo_pessoa` (int, não-incremental)<br>**Atributos:** `nome_pessoa`<br>**Relações:** `vinculos()`, `lancamentos()`, `cotaEspecial()` | Entidade central. Utiliza `codigo_pessoa` como chave primária manual para manter consistência com dados legados/Replicado. |
| **vinculos** | `App\Models\Vinculo` | **PK Composta:** `['codigo_pessoa', 'tipo_vinculo']`<br>**Atributos:** `tipo_vinculo` | Tabela de associação (não JSON). Mapeia os vínculos ativos de uma pessoa com a instituição. |
| **lancamentos** | `App\Models\Lancamento` | **PK:** `id`<br>**FKs:** `codigo_pessoa`, `usuario_id`<br>**Atributos:** `valor`, `tipo_lancamento` (0=Créd, 1=Déb), `data` | Registra transações. Possui Query Scope `scopeMesAtual` para regras de cota. O campo `usuario_id` liga ao operador (`User`). |
| **cotas** | `App\Models\Cota` | **PK:** `id`<br>**Atributos:** `tipo_vinculo`, `valor` | Define a regra de quantidade de cópias padrão para cada tipo de vínculo (ex: DOCENTE = 300). |
| **cota_especiais** | `App\Models\CotaEspecial` | **PK:** `id`<br>**FK:** `codigo_pessoa`<br>**Atributos:** `valor` | Exceções à regra padrão. Se existir para uma pessoa, sobrepõe a cota do vínculo. |
| **users** | `App\Models\User` | **PK:** `id`<br>**Atributos:** `codpes`, `name`, `email` | Usuário operador do sistema. Utiliza Traits `HasRoles` (Spatie) e `HasSenhaunica` (USP). Substitui a tabela `USUARIO` antiga. |
| **roles** | `App\Models\Role` | **PK:** `id`<br>**Atributos:** `name`, `guard_name` | Gerenciado pelo pacote `spatie/laravel-permission`. Define perfis como `ADM` e `OPR`. Substitui `PAPEL`. |
| **audits** | `OwenIt\Auditing\Models\Audit` | **PK:** `id`<br>**Polimórfico:** `auditable_type`, `auditable_id` | Rastreamento automático de alterações em Models. Substitui a tabela `LOG`. |

## 4. Entidades Descartadas e Removidas

As seguintes tabelas/entidades constavam no sistema legado mas **NÃO fazem parte** da nova arquitetura:

*   **OU / GRUPO / USUARIO_GRUPO**: O conceito de Unidades Organizacionais e Grupos foi simplificado ou removido em favor do sistema de Roles/Permissions padrão.
*   **MENSAGEM**: Descartada. O envio de e-mails será tratado, se necessário, por Notifications do Laravel, sem persistência em tabela de domínio.
*   **PESSOA_LANCAMENTO**: Tabela redundante removida. A relação é direta via FK em `lancamentos`.
*   **hibernate_sequence**: Tabela utilitária de Java/Hibernate, removida.
*   **REQUISICAO_SENHA / TOKEN**: Funcionalidades absorvidas pelo `Laravel Breeze` (Password Reset) e `Sanctum` (Tokens).

## 5. Conclusão

Este mapeamento reflete fielmente o código-fonte atual em `app/Models` e as migrações em `database/migrations`. A padronização dos nomes de colunas (ex: `codigo_pessoa`, `nome_pessoa`) e a definição explícita de chaves primárias não-incrementais são cruciais para o funcionamento correto do Eloquent e das relações no Filament.
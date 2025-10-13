# Mapeamento de Entidades do Banco de Dados para Models Eloquent

## 1. Introdução

Este documento define a arquitetura da camada de dados para a nova versão do sistema CotaG, a ser construída sobre o framework Laravel. O principal objetivo é mapear as tabelas do banco de dados legado para Models Eloquent, estabelecendo uma base sólida para o desenvolvimento da aplicação.

Esta abordagem formaliza os nomes dos Models, seus atributos, relacionamentos e, crucialmente, documenta as decisões de arquitetura sobre quais funcionalidades legadas serão mantidas e quais serão substituídas por componentes modernos do ecossistema Laravel, como Breeze (autenticação), Spatie/laravel-permission (controle de acesso) e pacotes de auditoria.

## 2. Visão Geral do Esquema

O esquema proposto para a nova aplicação em Laravel classifica as tabelas legadas em três categorias:

1.  **Entidades de Domínio (Mantidas):** Tabelas que representam o núcleo da lógica de negócio do CotaG, como `PESSOA`, `LANCAMENTO`, `COTA`, `COTA_ESPECIAL` e `VINCULO`. Estas serão mapeadas diretamente para Models Eloquent.

2.  **Funcionalidades de Framework (Substituídas):** Tabelas cuja funcionalidade é melhor e mais seguramente gerenciada por pacotes padrão do ecossistema Laravel. Isso inclui:
    * **Autenticação e Acesso (`USUARIO`, `PAPEL`, `USUARIO_PAPEL`, `REQUISICAO_SENHA`, `TOKEN`):** Serão substituídas pelo sistema de `User` do Laravel, integrado com o **Laravel Breeze** (autenticação local), **Laravel Sanctum** (tokens) e **Spatie/laravel-permission** (perfis e permissões).
    * **Auditoria (`LOG`):** Será substituída por um pacote de auditoria robusto como o `owen-it/laravel-auditing`, que oferece rastreamento automático de alterações nos Models.

3.  **Tabelas Obsoletas (Descartadas):** Tabelas que são resquícios do framework anterior (`hibernate_sequence`) ou que são redundantes na estrutura atual (`PESSOA_LANCAMENTO`). Estas não serão migradas.

## 3. Mapeamento das Entidades

A tabela a seguir detalha o mapeamento de cada tabela legada para sua representação na nova arquitetura Laravel.

| Tabela Legada | Model Laravel Proposto | Relacionamentos Eloquent | Observações |
| :--- | :--- | :--- | :--- |
| **USUARIO** | `App\Models\User` | `lancamentosRegistrados(): hasMany(Lancamento::class)`<br>`roles(): belongsToMany(Role::class)` | **Substituído.** Será o model `User` padrão do Laravel/Breeze, já com o trait `HasRoles`. A tabela `users` será customizada para incluir `codpes`. A coluna `salt` é obsoleta. |
| **PAPEL**, **USUARIO_PAPEL** | `Spatie\Permission\Models\Role` | `users(): belongsToMany(User::class)` | **Substituído.** A gestão de perfis (`ADM`, `OPR`) e a tabela pivô serão inteiramente gerenciadas pelo pacote `spatie/laravel-permission`. |
| **REQUISICAO_SENHA** | N/A | N/A | **Substituído.** A funcionalidade "Esqueci minha Senha" será gerenciada pela tabela `password_reset_tokens` nativa do Laravel Breeze. |
| **TOKEN** | N/A | N/A | **Substituído.** A gestão de tokens de sessão persistente ou API será gerenciada pelo Laravel Sanctum, que acompanha o Breeze. |
| **LOG** | `OwenIt\Auditing\Models\Audit` | `user(): belongsTo(User::class)` | **Substituído.** Será substituído pelo sistema de auditoria do pacote `owen-it/laravel-auditing`, que rastreia alterações nos Models automaticamente. |
| **PESSOA** | `App\Models\Pessoa` | `lancamentos(): hasMany(Lancamento::class)`<br>`cotaEspecial(): hasOne(CotaEspecial::class)`<br>`vinculos(): hasMany(Vinculo::class)` | **Mantido.** Entidade central do negócio. Chave primária (`codpes`) não é auto-incrementável (`public $incrementing = false`). |
| **LANCAMENTO** | `App\Models\Lancamento` | `pessoa(): belongsTo(Pessoa::class)`<br>`operador(): belongsTo(User::class)` | **Mantido.** Registra todas as transações de débito e crédito. |
| **COTA** | `App\Models\Cota` | - | **Mantido.** Armazena as regras de cotas padrão por vínculo. O relacionamento com `Vinculo` é lógico, não por FK. |
| **COTA_ESPECIAL** | `App\Models\CotaEspecial` | `pessoa(): belongsTo(Pessoa::class)` | **Mantido.** Cota de exceção para um indivíduo. |
| **VINCULO** | `App\Models\Vinculo` | `pessoa(): belongsTo(Pessoa::class)` | **Mantido.** Representa as relações de uma `Pessoa` com a instituição. |
| **OU**, **GRUPO**, **USUARIO_GRUPO** | `App\Models\UnidadeOrganizacional`<br>`App\Models\Grupo` | `grupos(): hasMany(Grupo::class)`<br>`unidadeOrganizacional(): belongsTo(Unidade...)`<br>`usuarios(): belongsToMany(User::class)` | **Mantidos.** Estrutura de organização interna dos operadores do sistema. Os nomes dos Models foram melhorados para maior clareza. |
| **MENSAGEM** | `App\Models\Mensagem` | - | **Mantido.** Pode ser integrado com o sistema de `Mailable` e `Notifications` do Laravel para registrar e-mails transacionais enviados. |
| **PESSOA_LANCAMENTO** | N/A | N/A | **Descartado.** Tabela redundante na lógica de negócio. A relação já é estabelecida pela FK `pessoa_codpes` na tabela `LANCAMENTO`. |
| **hibernate_sequence** | N/A | N/A | **Descartado.** Tabela utilitária específica do framework Java/Hibernate. Não será migrada. |

## 4. Estratégia de Migração de Dados

A transição dos dados do banco de dados legado para a nova estrutura Laravel seguirá uma abordagem em fases:

1.  **Criação de Seeders para Dados de Configuração:** Dados de tabelas de "regras", como `COTA`, `PAPEL` e `OU`, serão migrados através de **Seeders** do Laravel. Esses dados são relativamente estáticos e essenciais para a configuração inicial do sistema.

2.  **Comando de Migração Customizado para Dados Transacionais:** Para entidades complexas e com grande volume de dados (`PESSOA`, `USUARIO`, `LANCAMENTO`, etc.), será criado um comando Artisan customizado (ex: `php artisan cotag:migrate-legacy-data`). Este comando será responsável por:
    * Conectar-se ao banco de dados legado.
    * Ler os dados das tabelas antigas.
    * Transformar e mapear os dados para as novas estruturas de tabela e `foreign keys`.
    * Inserir os dados no novo banco de dados Laravel, mantendo a integridade dos relacionamentos.
    * Exibir o progresso e tratar possíveis erros durante a migração.

3.  **Gestão de Senhas de Usuários:** As senhas dos operadores (`USUARIO`) não podem ser migradas devido à diferença nos algoritmos de hashing. A estratégia será:
    * Migrar todas as contas de `USUARIO` para a nova tabela `users` com o campo `password` nulo ou com um valor inválido.
    * Implementar um fluxo onde, na primeira tentativa de login de um usuário migrado, ele seja forçado a passar pelo processo de "Esqueci minha Senha" para definir uma nova senha segura no formato do Laravel.

4.  **Dados Descartados:** Os dados das tabelas `LOG`, `REQUISICAO_SENHA`, `TOKEN`, `PESSOA_LANCAMENTO` e `hibernate_sequence` não serão migrados, pois suas funcionalidades serão substituídas por sistemas mais modernos e seguros na nova aplicação.

## 5. Conclusão

O mapeamento proposto fornece um caminho claro para a modernização da arquitetura de dados do CotaG. Ao manter as entidades de domínio principais e substituir funcionalidades genéricas por componentes robustos do ecossistema Laravel, a nova versão do sistema ganhará em segurança, manutenibilidade e escalabilidade. Esta estrutura de Models Eloquent servirá como a fundação para a implementação das regras de negócio e da interface do usuário na plataforma Laravel.
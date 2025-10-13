# Roadmap de Implementação (Backlog de Issues) - Novo Sistema CotaG

## 1. Introdução

Este documento transforma os documentos de análise e arquitetura em um backlog detalhado e acionável de tarefas de implementação. Ele servirá como o roteiro principal para o desenvolvimento da nova versão do sistema CotaG em Laravel, listando todas as *issues* que precisarão ser criadas e resolvidas para construir o sistema conforme a arquitetura planejada.

## 2. Visão Geral do Esquema

O roadmap está organizado em módulos que seguem uma ordem lógica de dependência: primeiro a fundação do sistema (banco de dados, models), seguida pela lógica de negócio core (services), depois a interface administrativa (Filament) e, por fim, a interface principal do operador (Livewire). Cada item da checklist representa uma *issue* atômica a ser criada no GitHub.

## 3. Roadmap de Implementação

### Módulo 1: Estrutura de Dados e Models (Fundação)
*(Foco: Estabelecer a base do banco de dados e do Eloquent para suportar todas as outras funcionalidades)*

- [ ] `[FEAT]` Criar migrations para as tabelas de domínio: `pessoas`, `vinculos`, `cotas`, `cotas_especiais`, `lancamentos`.
- [ ] `[FEAT]` Criar migrations para as tabelas de organização interna: `unidades_organizacionais` (OU) e `grupos`.
- [ ] `[FEAT]` Criar migration para a tabela de suporte `mensagens`, para registro de comunicações.
- [ ] `[FEAT]` Criar Models Eloquent (`Pessoa`, `Vinculo`, `Cota`, `CotaEspecial`, `Lancamento`) com seus respectivos relacionamentos (`hasMany`, `belongsTo`, `hasOne`).
- [ ] `[FEAT]` Criar Models Eloquent (`UnidadeOrganizacional`, `Grupo`, `Mensagem`) com seus respectivos relacionamentos.
- [ ] `[CHORE]` Adaptar a migration e o Model `User` (do Laravel Breeze) para incluir a coluna `codpes` e os relacionamentos necessários.
- [ ] `[CHORE]` Configurar chaves primárias não-incrementáveis (`public $incrementing = false`) e não-padrão (`$primaryKey`) nos models onde for aplicável (ex: `Pessoa`).
- [ ] `[TEST]` Escrever testes unitários para validar todos os relacionamentos Eloquent definidos (ex: `Pessoa` tem muitos `Lancamentos`).

### Módulo 2: Lógica de Negócio Core (Services)
*(Foco: Implementar a lógica central do sistema de forma desacoplada, reutilizável e testável)*

- [ ] `[FEAT]` Implementar o `ReplicadoService` com um método `buscarPessoa(criterio)` que encapsula a lógica de busca por `codpes`, nome ou e-mail, conforme a análise funcional.
- [ ] `[FEAT]` Implementar o `CotaService` com o método principal `calcularSaldo(Pessoa $pessoa)` que aplica a lógica completa: checa cota especial, determina a maior cota por vínculo e calcula o saldo com base nos lançamentos do mês.
- [ ] `[CHORE]` Criar o Query Scope `scopeMesAtual()` no Model `Lancamento` para otimizar e centralizar a busca de lançamentos do mês corrente, a ser usado pelo `CotaService`.
- [ ] `[FEAT]` Implementar o Accessor `getSaldoAttribute()` no Model `Pessoa` que delega a chamada para o `CotaService`, simplificando o acesso ao saldo em toda a aplicação (`$pessoa->saldo`).
- [ ] `[TEST]` Escrever testes unitários para o `ReplicadoService` utilizando um mock (`FakeReplicadoService`) para simular a interação com o sistema externo.
- [ ] `[TEST]` Escrever testes unitários exaustivos para o `CotaService`, cobrindo todos os cenários de cálculo de cota: com cota especial, com múltiplos vínculos, sem cota, com saldo negativo, etc.

### Módulo 3: Autenticação e Autorização
*(Foco: Configurar todo o sistema de acesso, perfis e permissões)*

- [ ] `[CHORE]` Implementar a lógica de login local para `USUARIO` (operadores), que utiliza `codpes` em vez de email.
- [ ] `[FEAT]` Implementar o fluxo completo de login com Senha Única da USP, incluindo o *callback* que busca ou cria o `User` local.
- [ ] `[FEAT]` Implementar a funcionalidade de "Recuperação de Senha" para o login local, utilizando o sistema nativo do Laravel Breeze.
- [ ] `[CHORE]` Criar as permissões `operar-sistema` e `acessar-admin` no `spatie/laravel-permission`.
- [ ] `[CHORE]` Atribuir a permissão `operar-sistema` ao papel `OPR` e ambas as permissões ao papel `ADM` via Seeder.
- [ ] `[CHORE]` Aplicar o middleware `can:operar-sistema` às rotas da interface do operador e a lógica de autorização do Filament para o painel administrativo (`canAccessPanel`).
- [ ] `[TEST]` Escrever testes de feature para garantir que o login local e com Senha Única funcionam corretamente.
- [ ] `[TEST]` Escrever testes para garantir que as rotas estão protegidas e apenas usuários com os papéis corretos podem acessá-las.

### Módulo 4: Interface Administrativa (Filament)
*(Foco: Criar a área de gestão completa para o perfil Administrador)*

- [ ] `[FEAT]` Estender o `UserResource` do Filament para gerenciar operadores, incluindo o campo `codpes` e a atribuição de papéis e grupos.
- [ ] `[FEAT]` Configurar o `RoleResource` (Spatie Filament Plugin) para permitir o CRUD dos papéis `ADM` e `OPR`.
- [ ] `[FEAT]` Criar o `CotaResource` para o gerenciamento (CRUD) das cotas padrão por vínculo.
- [ ] `[FEAT]` Criar o `CotaEspecialResource` para o gerenciamento (CRUD) das cotas de exceção.
- [ ] `[FEAT]` Criar o `UnidadeOrganizacionalResource` e `GrupoResource` para gerenciar a estrutura organizacional.
- [ ] `[CHORE]` Integrar o `owen-it/laravel-auditing` e criar um `AuditResource` no Filament para visualizar os logs de auditoria do sistema.
- [ ] `[FEAT]` Criar um `ExtratoResource` no Filament para a funcionalidade de "Extrato Geral", permitindo a visualização de todos os lançamentos com filtros de data.
- [ ] `[TEST]` Escrever testes de feature para o `CotaResource` e `CotaEspecialResource`, garantindo que um `ADM` pode criar, editar e deletar cotas.

### Módulo 5: Interface do Operador (Livewire)
*(Foco: Desenvolver a principal tela de operação do sistema de forma interativa)*

- [ ] `[FEAT]` Criar o componente `Livewire\Lancamento\ManageLancamentos` que será a tela principal.
- [ ] `[FEAT]` Implementar a funcionalidade de busca de pessoa na interface, que chama o `ReplicadoService` e exibe os resultados dinamicamente.
- [ ] `[FEAT]` Ao selecionar uma pessoa, a interface deve exibir seu perfil (nome, Nº USP, vínculos) e seu saldo atual (via `$pessoa->saldo`).
- [ ] `[FEAT]` Implementar os formulários e ações para registrar Lançamentos de Débito e Crédito.
- [ ] `[FEAT]` Criar o `StoreLancamentoRequest` para centralizar a validação dos dados de entrada do formulário de lançamento.
- [ ] `[FEAT]` Exibir a tabela com o histórico de lançamentos da pessoa para o mês corrente, que se atualiza automaticamente via Livewire após cada novo lançamento.
- [ ] `[TEST]` Escrever testes de feature para o componente `ManageLancamentos`, simulando o fluxo completo: buscar um usuário, ver o saldo, registrar um débito e verificar se o saldo foi atualizado na tela.

### Módulo 6: Finalização e Migração
*(Foco: Preparar o sistema para produção e migrar os dados legados)*

- [ ] `[CHORE]` Criar Seeders para popular os dados iniciais de `cotas`, `papeis`, `unidades_organizacionais` e `grupos`.
- [ ] `[CHORE]` Desenvolver o comando `php artisan cotag:migrate-legacy-data` para executar a migração dos dados transacionais (`PESSOA`, `USUARIO`, `LANCAMENTO`).
- [ ] `[DOC]` Revisar e finalizar toda a documentação do projeto (`README.md` e outros documentos em `docs/`) com base na implementação final.
- [ ] `[FIX]` Realizar uma fase de testes manuais e correção de bugs em todas as funcionalidades implementadas.
- [ ] `[CHORE]` Configurar e testar o envio de e-mails para a funcionalidade de recuperação de senha em ambiente de produção.
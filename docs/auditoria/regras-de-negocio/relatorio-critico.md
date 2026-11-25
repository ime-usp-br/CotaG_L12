# Relatório Crítico de Regras de Negócio e Fluxos — CotaG

**Versão:** 1.0
**Data:** 2026-11-25
**Objeto:** regras de negócio, fluxos, atores, estados, permissões, ciclo de vida dos dados e invariantes do CotaG.
**Método:** leitura estática integral do repositório, confronto entre fontes e crítica adversarial. Nenhum código, migration, configuração ou dado foi alterado.

> **Aviso epistemológico:** README, AGENTE.md, `docs/analise/*`, `docs/arquitetura/*`, comentários, nomes de variáveis, seeders, testes e mensagens de UI não foram tratados como verdade. Cada afirmação foi confrontada com o comportamento observável ou marcada como hipótese, relato de segunda mão ou lacuna.

---

## 1. Sumário executivo

**Veredito:** o CotaG implementa um núcleo técnico funcional de “lançamento mensal com cota variável”, mas não implementa um sistema de cotas auditável, determinístico e reconstruível. As regras centrais do domínio — o que é cota, o que é crédito, o que é mês, o que é saldo negativo, qual é a autoridade sobre o vínculo e quem pode decidir o quê — não estão definidas de forma única. A documentação descreve, em vários pontos, um sistema diferente do que o código faz.

O sistema é operável no caso feliz: um operador busca uma pessoa, vê um saldo calculado e registra débito ou crédito. Fora do caso feliz, ele não é confiável: o histórico pode ser apagado em cascata, a cota pode mudar retroativamente, vínculos podem ficar obsoletos para sempre, uma falha parcial do Replicado pode produzir cota zero silenciosa e a cota especial pode somar registros e reduzir o benefício do usuário.

### Achados mais graves

| ID | Severidade | Achado |
|---|---|---|
| ACH-001 | Bloqueante | Dois vocabulários de papéis incompatíveis (`ADM`/`OPR` × `Admin`/`Operador`). Seguir o README produz um administrador que entra no painel, mas é negado pelas policies; o operador não entra no painel. |
| ACH-002 | Bloqueante | O operador não acessa a gestão de cotas nem o extrato, apesar de a documentação e o menu prometerem isso. O link “Painel de Admin” aparece para o operador e leva a 403. |
| ACH-003 | Bloqueante | Excluir um usuário apaga em cascata todos os lançamentos que ele registrou; o próprio operador pode excluir a própria conta pelo perfil. |
| ACH-004 | Bloqueante | Excluir uma pessoa apaga em cascata lançamentos, cota especial e vínculos. Não há proteção de integridade histórica. |
| ACH-005 | Bloqueante | Não existe fechamento mensal, snapshot nem versionamento de regra. Alterar ou excluir uma cota hoje muda qualquer recomputação do passado; o saldo de um mês fechado não é reconstruível pelo sistema. |
| ACH-006 | Bloqueante | Cota especial não é única, não tem vigência, motivo, autor explícito ou revogação; múltiplos registros são somados e uma especial menor que a regular reduz a cota, contrariando “maior benefício”. |
| ACH-007 | Bloqueante | “Crédito” não tem origem financeira definida e expira na virada do mês; se for compra, o usuário perde o que pagou. |
| ACH-008 | Bloqueante | Saldo negativo é permitido, mas não há dívida, cobrança, bloqueio, notificação ou regularização; o negativo desaparece na virada do mês. |
| ACH-009 | Bloqueante | “Mês” é o mês civil do fuso da aplicação, todo lançamento usa a data de criação e não há lançamento retroativo nem fechamento. |
| ACH-010 | Alta | Vínculos de pessoa já existente não são atualizados nos caminhos normais; não há estado inativo. Uma pessoa desligada pode continuar consumindo com a cota antiga. |
| ACH-011 | Alta | Falha parcial do Replicado na consulta de vínculos é engolida e pode produzir pessoa sem vínculo e cota zero, sem erro visível. |
| ACH-012 | Alta | Não há filtro de unidade; qualquer pessoa da USP, de qualquer unidade, pode ser importada e receber cota/crédito. `REPLICADO_CODUNDCLG` existe no ambiente, mas não é usado. |
| ACH-019 | Alta | Os seeders de cota são declaradamente “simulação”; mesmo assim o deploy manda rodá-los em produção. `SERVIDOR` e vários vínculos não têm cota e ficam com zero. |

### O que precisa ser decidido antes de qualquer código

1. O que é cota: direito mensal não acumulável, orçamento, limite de consumo ou saldo?
2. O que é crédito: compra, bonificação, ajuste ou transferência? Expira?
3. O que é saldo negativo: dívida, tolerância, erro ou estado proibido?
4. O que é “mês”: calendário civil, ciclo de fechamento ou período letivo? Qual o instante de corte?
5. Qual é a precedência entre cota especial, cota regular e créditos?
6. O histórico financeiro é imutável? Como se corrige um lançamento?
7. Quem é a autoridade sobre o vínculo: Replicado, sistema ou ambos? Com qual frequência?
8. Quem pode definir política, conceder exceção e conceder crédito? Operador pode beneficiar a si mesmo?

---

## 2. Escopo, fontes e método

### 2.1 Escopo

**Dentro do escopo:** regras, políticas, fluxos ponta a ponta, atores, estados, transições, permissões, ciclo de vida dos dados, invariantes, decisões de negócio, dependências externas que alteram regra.

**Fora do escopo:** qualidade de código, estilo, arquitetura, performance, segurança de implementação, cobertura de testes e escolha de framework. Código foi usado apenas como evidência de comportamento. Defeitos sem consequência de negócio foram registrados como “fora de escopo” no anexo.

### 2.2 Fontes inventariadas

| Fonte | O que afirma | Classificação | Confiança | Como verificar |
|---|---|---|---|---|
| `README.md` | CotaG em produção; cota regular por vínculo; cota especial sobrepõe; saldo = cota + créditos − débitos; RBAC `ADM`/`OPR`; logs de todas as operações críticas | Intenção declarada | Média | Confrontar com código, seeders, policies e rotas |
| `AGENTE.md` | Regras 3.1–3.3; “maior valor entre vínculos”; saldo negativo permitido; histórico preservado; local-first | Intenção declarada e relato | Média | Confrontar com `CotaService`, migrations, `ReplicadoService` |
| `docs/analise/01` | Análise do legado Java/JSF: login local por NUSP, LOG, botões de extrato, geração de senha | Relato de segunda mão | Baixa | Código legado não está no repositório; impossível verificar |
| `docs/analise/02` | Dicionário de dados do legado; tabela `PESSOA_LANCAMENTO`; `MENSAGEM`; `OU`; `GRUPO` | Relato de segunda mão | Baixa | Comparar com migrations atuais; legado ausente |
| `docs/analise/03` | Regras extraídas “do código Java”: prioridade absoluta da especial; maior benefício; saldo negativo; renovação implícita | Relato de segunda mão | Baixa | Código legado ausente; confrontar com `CotaService` |
| `docs/analise/04` | Operador gerencia cotas e vê extrato; Admin herda operador; Admin desativa contas | Relato de segunda mão / intenção | Baixa | Confrontar com middleware, policies e UI |
| `docs/arquitetura/01` | Mapeamento “final” e “fonte da verdade”; `ADM`/`OPR`; `audits` substitui `LOG` | Intenção declarada | Média | Confrontar com models e seeders |
| `docs/arquitetura/02` | Arquitetura final; exportação no extrato; `buscarPessoa` no `ReplicadoService` | Intenção declarada | Média | Confrontar com código real |
| `docs/termo_abertura_projeto.md` | Escopo de “Laravel 12 USP Starter Kit”; exclui funcionalidades de negócio | Intenção declarada de outro produto | Baixa para CotaG | Confrontar com README e código |
| `routes/web.php`, `routes/auth.php` | Rotas e middleware reais | Implementação observável | Alta | Leitura direta |
| `app/Models/*` | Relações, casts, escopos, auditabilidade | Implementação observável | Alta | Leitura direta |
| `app/Services/*` | Regras de cota, saldo e integração | Implementação observável | Alta | Leitura direta |
| `app/Livewire/*` | Fluxo do operador, busca, importação, lançamento | Implementação observável | Alta | Leitura direta |
| `app/Filament/*` | CRUDs, tabelas, formulários, auditoria | Implementação observável | Alta | Leitura direta |
| `app/Policies/*`, `app/Providers/*` | Autorização efetiva | Implementação observável | Alta | Leitura direta |
| `database/migrations/*` | Schema, FKs, cascatas, unicidade | Implementação observável | Alta | Leitura direta |
| `database/seeders/*` | Dados de exemplo e papéis iniciais | Dado de exemplo / decisão não documentada | Média | Comparar com deploy e docs |
| `database/factories/*` | Dados de teste | Dado de exemplo | Baixa | Não usar como regra |
| `tests/*` | Intenção de regras antigas e novos fluxos | Intenção / evidência fraca | Baixa/Média | Testes não executados neste ambiente; não são verdade |
| `vendor/uspdev/senhaunica-socialite` | Comportamento real do login USP e permissões | Implementação externa observável | Alta | Leitura do pacote |
| `docker/production/entrypoint.sh`, `docker-compose.prod.yml` | Operação de deploy, migrations, seeders | Implementação observável | Alta | Leitura direta |
| `storage/app/private/debug/oauth/7762448.json` | Artefato local de debug OAuth com dados pessoais | Dado observado | Alta | Arquivo existe localmente; não versionado |

### 2.3 Limitações

- **Sem runtime PHP:** `php` não está instalado no ambiente (`php: command not found`). Não foi possível executar `artisan route:list`, a suíte de testes ou consultas ao banco. Toda a análise é estática.
- **Sem código legado:** não existe nenhum `.java`, `.xhtml` ou `.jsf` no repositório. Toda afirmação sobre o legado é relato de segunda mão e não foi verificada.
- **Sem banco de produção nem dumps:** os únicos dados disponíveis são seeders, factories e um artefato de OAuth local. Não é possível verificar valores reais de cota, vínculos reais ou histórico.
- **Sem issues/wiki:** não foram consultadas issues, PRs ou Wiki do GitHub.
- **Sem acesso ao Replicado real:** o schema, os valores de `tipvin`, `sitatl`, `nomcaa`, `nomcnhpes` e a semântica de vínculo ativo não foram verificados.
- **Testes não executados:** os testes foram lidos como evidência de intenção, não como prova de funcionamento. Há testes que usam uma API que não existe (`ReplicadoService::buscarPessoa`) e papéis que os seeders não criam (`ADM`/`OPR`).

### 2.4 Como ler este relatório

Sempre que possível, cada afirmação relevante tem evidência `arquivo:linha`. Quando a conclusão depende de documentação do próprio projeto, isso está marcado como **relato** ou **hipótese**. Quando não é possível decidir, está marcado como **indeterminado** com a lacuna correspondente.

---

## 3. Objetivo reconstruído e domínio

### 3.1 Objetivo declarado

O README declara: “Controlar e auditar o consumo de recursos da gráfica, garantindo que docentes, funcionários e alunos utilizem suas cotas de acordo com as políticas institucionais” (`README.md:15`). O mesmo documento afirma que o sistema “já está em produção” (`README.md:25`).

O AGENTE.md declara que o projeto está em “fase de desenvolvimento ativo” (`AGENTE.md:33`) e que a “migração das regras de negócio do sistema legado” está “concluída” (`AGENTE.md:38`). O termo de abertura, por sua vez, descreve um “Laravel 12 USP Starter Kit” e exclui “funcionalidades de negócio específicas” (`docs/termo_abertura_projeto.md:1,72-74`).

**FATO OBSERVADO:** as três fontes não descrevem o mesmo produto nem o mesmo estágio. **INTERPRETAÇÃO:** não há um objetivo normativo único. **HIPÓTESE:** o objetivo real é operar o balcão da gráfica do IME-USP com um livro de lançamentos mensal e uma política de cotas por vínculo, com exceções individuais. **LACUNA:** não há documento aprovado que diga o que o sistema deve garantir em caso de conflito, falha ou disputa.

### 3.2 Objetivo reconstruído (hipótese)

A partir dos fluxos observáveis, o sistema parece existir para:

1. Identificar uma pessoa da comunidade USP por NUSP, nome ou e-mail.
2. Determinar sua cota mensal a partir do vínculo (regular) ou de uma exceção individual (especial).
3. Registrar consumo (débito) e adição de saldo (crédito) no balcão.
4. Exibir o saldo do mês corrente e o histórico do mês.
5. Permitir que um administrador mantenha a política de cotas, usuários, papéis e auditoria.
6. Sobreviver à virada do mês renovando a cota implicitamente.

**O que o sistema deveria garantir (reconstrução):**

- O saldo exibido é derivável dos lançamentos e da regra vigente.
- Nenhum lançamento é perdido ou alterado sem rastro.
- A cota de uma pessoa em uma data é determinística.
- Alterar uma regra hoje não muda o passado.
- Uma pessoa inelegível não consome cota institucional.
- Uma falha da fonte externa não produz silenciosamente uma cota errada.
- Quem concede benefício não é o mesmo que se beneficia, ou há controle compensatório.
- O histórico permite resolver uma disputa sem acesso ao banco.

**O que o sistema explicitamente não é (reconstrução):**

- Não é um sistema de autoatendimento do beneficiário: `docs/analise/04:9-11` diz que o usuário final não interage; o código, porém, permite registro público, o que contradiz essa intenção.
- Não é um sistema de cobrança: não há fatura, pagamento, boleto ou débito financeiro.
- Não é um sistema de estoque de papel/tinta: não há insumo, equipamento ou fila de impressão.
- Não é um sistema multiunidade: não há unidade organizacional, centro de custo ou rateio.

### 3.3 Atores e interesses

| Ator | Interesse | Como aparece no sistema |
|---|---|---|
| Beneficiário (`Pessoa`) | Ter a cota correta, não ser cobrado indevidamente, contestar | Não tem login; é um registro identificado por `codigo_pessoa` |
| Operador da gráfica (`Operador`) | Atender rápido, registrar consumo, não errar | Acesso a `/lancamento` via permissão `operar-sistema` |
| Administrador (`Admin`) | Manter política, usuários, exceções e auditoria | Acesso ao painel Filament `/admin` |
| Gestão da gráfica / instituição | Controlar custo, cobrar excesso, prestar contas | Não há ator explícito; aparece como “controle administrativo” |
| Auditoria / ouvidoria | Reconstruir o que aconteceu e quem autorizou | Só há logs de modelo; não há processo de auditoria |
| Suporte técnico | Corrigir dados, importar, explicar | Acesso via tinker/banco; não há ferramenta de correção |
| USP (Replicado) | Ser a fonte de identidade e vínculo | Fonte externa consultada por SQL |
| USP (Senha Única) | Autenticar | OAuth1; cria usuário local sem papel de negócio |
| Sistema / integração | Executar cálculo e auditoria | `CotaService`, `ReplicadoService`, `owen-it/laravel-auditing` |

### 3.4 Vocabulário do domínio e ambiguidades

| Termo | Uso no sistema | Ambiguidade |
|---|---|---|
| **Cota** | Valor base mensal calculado; também nome da tabela de política (`cotas`) e do registro especial (`cota_especials`) | É direito, orçamento, limite ou saldo? Não é armazenada como saldo; é recomputada |
| **Cota regular** | `Cota.valor` associado a `tipo_vinculo`; aplica-se o maior valor entre os vínculos | “Maior” é uma escolha; poderia ser soma, vínculo principal ou último vínculo |
| **Cota especial** | `CotaEspecial.valor` por pessoa; sobrepõe a regular | Pode haver mais de uma; o serviço soma. É override, piso, teto ou adicional? |
| **Crédito** | `Lancamento` tipo `0`, somado ao saldo do mês | Compra, bonificação, ajuste ou transferência? Expira? Tem recibo? |
| **Débito** | `Lancamento` tipo `1`, subtraído do saldo do mês | Consumo, ajuste, perda ou estorno? Pode ser negativo? |
| **Saldo** | `cota + créditos − débitos` do mês corrente | Saldo disponível, dívida ou resultado? Pode ser negativo |
| **Mensal** | Mês civil do calendário no fuso `America/Sao_Paulo` | Ciclo de fechamento? Período letivo? Mês comercial? |
| **Renovação** | Consequência implícita da consulta por mês | Não há evento, fechamento ou carryover |
| **Prioridade absoluta** | Documentação diz que a especial tem prioridade | O código soma múltiplas especiais e permite especial menor que a regular |
| **Maior benefício** | Documentação diz que o sistema garante o maior benefício | Falso quando a especial é menor que a regular |
| **Vínculo ativo** | `sitatl <> 'D'` no Replicado, congelado no momento da importação | Não há data de início/fim nem estado local; “ativo” é um retrato |
| **Histórico** | Lançamentos do mês na tela do operador; todos no extrato | “Histórico completo” não inclui regra vigente, cota ou saldo da época |
| **Auditoria** | `audits` de modelos selecionados | Não inclui login, vínculo, atribuição de papel ou exclusão em cascata |
| **Produção** | README afirma; deploy possui stack | AGENTE diz desenvolvimento ativo; seeders de demo são instruídos no deploy |

### 3.5 Invariantes que o sistema deveria preservar

| ID | Invariante | Situação observada | Evidência |
|---|---|---|---|
| I-01 | O saldo exibido é derivável dos lançamentos e da regra vigente | Parcial: é derivável, mas a “regra vigente” não é versionada | `app/Services/CotaService.php:51-70` |
| I-02 | Nenhum lançamento é perdido | Violada: exclusão de usuário/pessoa apaga em cascata | `database/migrations/2025_10_14_165401_create_lancamentos_table.php:20-28` |
| I-03 | A cota é determinística para a mesma pessoa e data | Parcial: determinística para o estado atual, mas não para o estado da data | `app/Services/CotaService.php:79-109` |
| I-04 | Alterar uma regra não muda o passado | Violada: não há vigência nem snapshot | `app/Models/Cota.php:19-32`, `app/Models/CotaEspecial.php:21-42` |
| I-05 | Existe no máximo uma regra efetiva por vínculo e uma especial por pessoa | Violada: não há unicidade; múltiplas especiais somam | `database/migrations/2025_10_14_164147_create_cotas_table.php:14-19`, `database/migrations/2025_10_14_164919_create_cota_especials_table.php:14-23`, `app/Services/CotaService.php:96-109` |
| I-06 | Falha da fonte externa não produz cota silenciosamente errada | Violada: `buscarVinculos` engole exceção e retorna vazio | `app/Services/ReplicadoService.php:212-237` |
| I-07 | Todo débito tem ator, beneficiário, valor, data e motivo | Parcial: ator, beneficiário, valor e data existem; motivo não | `app/Models/Lancamento.php:36-42` |
| I-08 | Quem concede benefício não se beneficia sem controle | Violada: operador pode lançar crédito para qualquer pessoa, inclusive a si | `app/Livewire/Lancamento/ManageLancamentos.php:264-293` |
| I-09 | Toda mudança de política e permissão é rastreável | Parcial: cota e usuário são auditados; vínculo e atribuição de papel não | `app/Models/Pessoa.php:24-107`, `app/Models/Vinculo.php:19-53`, `database/seeders/RoleSeeder.php:22-40` |
| I-10 | “Mês” é explícito e o corte é determinístico | Parcial: fuso definido, mas sem fechamento e sem retroatividade | `config/app.php:68`, `app/Models/Lancamento.php:73-77` |
| I-11 | Só pessoas elegíveis recebem cota institucional | Violada: não há filtro de unidade; qualquer pessoa da USP pode ser importada | `app/Services/ReplicadoService.php:94-98,133-136,174-176` |
| I-12 | Pessoa sem vínculo tem estado explícito, não cota zero silenciosa | Violada: cota zero e consumo permitido | `app/Services/CotaService.php:79-90` |

---

## 4. Atores, papéis e permissões

### 4.1 Matriz real de acesso observada

| Capacidade | Admin (`Admin`) | Operador (`Operador`) | `ADM` | `OPR` | `usp_user` / `external_user` | Senha Única (permissão `user`) |
|---|---|---|---|---|---|---|
| Entrar em `/admin` | Sim (middleware `ADM\|Admin`) | **Não** (403) | Sim (middleware) | Não | Não | Não |
| Ver dashboard do painel | Sim | Não | Sim (mas recursos negados) | Não | Não | Não |
| CRUD de cotas regulares | Sim (policy) | Sim na policy, **mas não chega ao painel** | **Não** (policy exige `Admin`/`Operador`) | Não | Não | Não |
| CRUD de cotas especiais | Sim (policy) | Sim na policy, **mas não chega ao painel** | **Não** (policy) | Não | Não | Não |
| Ver extrato | Sim | Sim na policy, **mas não chega ao painel** | **Não** (policy) | Não | Não | Não |
| CRUD de usuários | Sim | Não | **Não** (policy) | Não | Não | Não |
| CRUD de papéis/permissões | Sim | Não | **Não** (policy) | Não | Não | Não |
| Ver logs de auditoria | Sim | Não | **Não** (policy) | Não | Não | Não |
| Tela `/lancamento` | Sim (permissão `operar-sistema` via role) | Sim | **Não** (role `ADM` não tem `operar-sistema` por seeder) | Sim se tiver permissão | Não | Não |
| Registrar débito/crédito | Sim | Sim | Não por padrão | Sim se tiver permissão | Não | Não |
| Excluir a própria conta | Sim (perfil) | Sim (perfil) | Sim | Sim | Sim | Sim |
| Assumir identidade (`/loginas`) | Depende do gate `admin` do pacote Senha Única | Não | Depende | Não | Não | Não |
| Gerenciar permissões via `/senhaunica-users` | Depende do gate `admin` do pacote | Não | Depende | Não | Não | Não |

**FATO OBSERVADO:** o papel `Operador` recebe a permissão `operar-sistema` (`database/seeders/RoleSeeder.php:35-39`), mas o painel Filament exige `ADM|Admin` (`app/Providers/Filament/AdminPanelProvider.php:124`) e `User::canAccessPanel` também nega `Operador` (`app/Models/User.php:80-88`). As policies de domínio aceitam `Admin` e `Operador` (`app/Policies/CotaPolicy.php:15-71`, `app/Policies/CotaEspecialPolicy.php:15-71`, `app/Policies/LancamentoPolicy.php:15-71`), mas o operador nunca chega ao painel.

**FATO OBSERVADO:** o papel `ADM` é aceito pelo middleware e por `canAccessPanel` (`app/Models/User.php:80-88`), mas nenhuma policy o aceita. As policies exigem `Admin` (e `Operador` no domínio). O README manda atribuir `ADM` (`README.md:190-196`).

**FATO OBSERVADO:** o menu mostra “Painel de Admin” para `Admin` e `Operador` (`resources/views/livewire/layout/navigation.blade.php:80-82`), mas o operador recebe 403 no painel. O commit `80113f1` declara “Permite acesso do operador ao painel admin”, mas o middleware atual não permite.

**FATO OBSERVADO:** os seeders criam `Admin` e `Operador` (`database/seeders/RoleSeeder.php:34-35`); os testes e o README usam `ADM` e `OPR` (`tests/Feature/Filament/AdminAccessTest.php:25-33`, `README.md:42`). Os testes criam esses papéis manualmente, então não provam que o sistema instalado os tenha.

### 4.2 Dois sistemas de autorização

1. **Spatie roles/permissions no guard `web`:** `Admin`, `Operador`, `usp_user`, `external_user`; permissão `operar-sistema`. É o sistema usado por `/lancamento` e pelas policies do Filament.
2. **Permissões do pacote Senha Única no guard `senhaunica`:** `admin`, `boss`, `manager`, `poweruser`, `user`, `Docente`, `Servidor`, etc. É aplicado no login OAuth (`vendor/uspdev/senhaunica-socialite/src/Traits/HasSenhaunica.php:160-174`) e cria gates como `admin` (`vendor/uspdev/senhaunica-socialite/src/Providers/AuthServiceProvider.php:20-35`). Esse gate protege `/loginas` e `/senhaunica-users` (`vendor/uspdev/senhaunica-socialite/src/Http/Controllers/UserController.php:15-35`).

**INTERPRETAÇÃO:** há um canal de administração paralelo, controlado por `SENHAUNICA_ADMINS` e/ou permissões do guard `senhaunica`, que não aparece na documentação do CotaG e não é auditado. **HIPÓTESE:** é resíduo do starter kit. **LACUNA:** não se sabe se a instituição pretende usá-lo.

---

## 5. Mapa de fluxos

### 5.1 Visão geral

```mermaid
flowchart TD
    A[Visitante] -->|/login| B[Senha Única OAuth]
    A -->|/login/local| C[Login local por e-mail]
    A -->|/register| D[Registro público]
    B --> E[User local + permissões senhaunica]
    C --> F[Sessão web]
    D --> G[usp_user / external_user]
    E --> H{Role Operador/Admin?}
    F --> H
    H -->|Sim| I[/lancamento]
    H -->|Não| J[/dashboard]
    I --> K[Busca pessoa]
    K --> L[Local + Replicado]
    L --> M[Pessoa + vínculos]
    M --> N[CotaService: cota + créditos - débitos]
    N --> O[Lançamento débito/crédito]
    O --> P[(lancamentos)]
    N --> Q[Histórico do mês]
    Admin[Admin] --> R[/admin]
    R --> S[Cotas regulares]
    R --> T[Cotas especiais]
    R --> U[Usuários/papéis/permissões]
    R --> V[Extrato]
    R --> W[Auditoria]
    S --> P
    T --> N
```

### F-01 — Autenticação por Senha Única

**Ator:** operador/admin/visitante USP.
**Gatilho:** acesso a `/login` ou botão “Login com Senha Única”.
**Pré-condições:** OAuth configurado (`SENHAUNICA_KEY`, `SECRET`, `CALLBACK_ID`).
**Passos observáveis:**
1. `GET /login` redireciona ao provedor OAuth (`vendor/uspdev/senhaunica-socialite/routes/web.php:6`).
2. Callback cria ou atualiza `User` por `codpes`, com e-mail e nome do OAuth (`SenhaunicaController.php:50-55`).
3. `Auth::login($user, true)` autentica (`SenhaunicaController.php:57`).
4. `aplicarPermissoes` cria permissões padrão e sincroniza permissões do guard `senhaunica` (`HasSenhaunica.php:160-174`).
5. Redireciona para a URL guardada ou `/` (`SenhaunicaController.php:64-68`).
**Regras aplicadas:** RN-033, RN-048.
**Estados e transições:** visitante → autenticado; usuário pode ser criado; sem papel de negócio.
**Pós-condições:** sessão autenticada; usuário existe em `users`; permissões `senhaunica` aplicadas.
**Efeitos colaterais:** escrita de JSON de OAuth em `storage` se `SENHAUNICA_DEBUG` não for falso (`vendor/uspdev/senhaunica-socialite/src/Server.php:61-62`); atualização de nome/e-mail a cada login.
**Exceções:** falha OAuth; e-mail já usado por outro `User` viola `unique` (`database/migrations/0001_01_01_000000_create_users_table.php:17`).
**Dados e origem:** `users` local; identidade do OAuth; permissões do pacote.
**Evidência:** rotas do pacote, `SenhaunicaController.php`, `HasSenhaunica.php`, `User.php`.
**Suficiente para entender a aplicação?** **Não.** O fluxo autentica, mas não atribui papel de negócio; a passagem de “usuário autenticado” para “operador” não está no fluxo. Também não há filtro de unidade.

### F-02 — Login local

**Ator:** operador/admin com senha local.
**Gatilho:** `/login/local`.
**Pré-condições:** usuário com `password` não nulo e e-mail cadastrado.
**Passos observáveis:**
1. Formulário Livewire valida e-mail e senha (`app/Livewire/Forms/LoginForm.php:29-45`).
2. `Auth::attempt` autentica.
3. Redireciona incondicionalmente para `/lancamento` (`resources/views/livewire/pages/auth/login.blade.php:25`).
**Regras aplicadas:** RN-048.
**Pós-condições:** sessão autenticada.
**Exceções:** credenciais inválidas, rate limit de 5 tentativas (`LoginForm.php:52-65`); usuário sem `operar-sistema` recebe 403 na rota de destino.
**Dados e origem:** `users` local.
**Evidência:** `LoginForm.php`, `login.blade.php`, `routes/web.php:19-25`.
**Suficiente?** **Não.** Não há verificação de papel antes do redirecionamento; qualquer usuário autenticado é mandado para uma rota que pode negá-lo.

### F-03 — Registro público

**Ator:** visitante.
**Gatilho:** `/register`.
**Pré-condições:** nenhuma; rota `guest` (`routes/auth.php:7-9`).
**Passos observáveis:**
1. Formulário pede nome, e-mail, senha; se e-mail `usp.br` ou checkbox “Sou da USP”, pede `codpes` (`resources/views/livewire/pages/auth/register.blade.php:49-91`).
2. Se USP, valida `codpes` + e-mail contra o Replicado (`register.blade.php:67-91`).
3. Cria `User` e atribui `usp_user` ou `external_user` (`register.blade.php:108-131`).
4. Faz login e redireciona para `/dashboard` (`register.blade.php:133-137`).
**Regras aplicadas:** RN-034.
**Pós-condições:** usuário criado, autenticado, sem acesso a `/lancamento` (não tem `operar-sistema`).
**Efeitos colaterais:** criação de contas sem aprovação; possível colisão com conta criada por Senha Única.
**Exceções:** Replicado indisponível bloqueia registro USP; e-mail duplicado; `codpes` duplicado não é bloqueado (não há unique).
**Evidência:** `register.blade.php`, `RoleSeeder.php:30-31`.
**Suficiente?** **Não.** O objetivo do registro não está declarado em nenhum documento do CotaG; a documentação de perfis diz que o beneficiário não interage com o sistema (`docs/analise/04:9-11`), o que torna o fluxo órfão.

### F-04 — Recuperação de senha e verificação de e-mail

**Ator:** usuário local.
**Gatilho:** `/forgot-password`, `/reset-password/{token}`, `/verify-email`.
**Passos observáveis:** fluxo Breeze/Volt padrão (`routes/auth.php:14-30`); e-mail de verificação e de redefinição.
**Regras aplicadas:** senha mínima de 8 com letras, maiúsculas/minúsculas, números e símbolos (`app/Providers/AppServiceProvider.php:37-44`); verificação obrigatória para `/dashboard` e `/lancamento` (`routes/web.php:22,38`).
**Pós-condições:** senha alterada ou e-mail verificado.
**Exceções:** SMTP não configurado; `MAIL_MAILER=log` em desenvolvimento (`.env.example:58`).
**Suficiente?** **Parcialmente.** O fluxo técnico é compreensível, mas não há regra de negócio sobre quem pode ter senha local, se operadores devem usar Senha Única ou se o registro local é permitido.

### F-05 — Gestão de usuários, papéis e permissões

**Ator:** Admin (na prática).
**Gatilho:** `/admin/users`, `/admin/roles`, `/admin/permissions`.
**Pré-condições:** papel `Admin`; painel acessível.
**Passos observáveis:**
1. Lista/cria/edita usuários; campos `name`, `email`, `codpes`, `email_verified_at`, `password`, `roles` (`app/Filament/Resources/Users/Schemas/UserForm.php:17-43`).
2. Lista/edita papéis e permissões (`RoleResource.php:32-85`, `PermissionResource.php:32-77`).
3. Exclui usuário; a FK de `lancamentos.usuario_id` apaga os lançamentos (`database/migrations/2025_10_14_165401_create_lancamentos_table.php:24-28`).
**Regras aplicadas:** RN-030, RN-036, RN-046, RN-047.
**Pós-condições:** usuário/papel alterado; lançamentos podem ser apagados.
**Efeitos colaterais:** atribuição de papel via relação `roles` não gera auditoria (o `User` não é salvo; o pivot não é auditado); alteração de permissões de papel também não.
**Exceções:** `UserPolicy` impede excluir a si mesmo (`app/Policies/UserPolicy.php:44-52`), mas não impede autodemissão nem exclusão de outro admin.
**Evidência:** `UserResource`, `RoleResource`, `PermissionResource`, policies.
**Suficiente?** **Não.** Falta a regra de quem pode ser operador, quem aprova, como desativar, como tratar o último admin e como auditar mudanças de papel.

### F-06 — Busca de pessoa por NUSP, nome ou e-mail

**Ator:** operador.
**Gatilho:** formulário de busca em `/lancamento`.
**Pré-condições:** pelo menos 3 caracteres (`ManageLancamentos.php:141-147`).
**Passos observáveis:**
1. Busca local por `codigo_pessoa` exato ou `nome_pessoa LIKE` (`ManageLancamentos.php:153-158`).
2. Se numérico, chama `buscarPessoaPorCodpes`; se contém `@`, `buscarPessoaPorEmail`; senão `buscarPessoasPorNome` (`ManageLancamentos.php:163-175`).
3. `buscarPessoaPorCodpes` tenta local primeiro e retorna sem consultar o Replicado se a pessoa existe (`ReplicadoService.php:77-91`).
4. Unifica resultados; ignora o resultado do Replicado quando o `codpes` já existe localmente (`ManageLancamentos.php:203-215`).
5. Se um resultado, seleciona; se vários, mostra lista (`ManageLancamentos.php:218-224`).
**Regras aplicadas:** RN-018 a RN-023.
**Estados e transições:** ocioso → resultados → selecionado.
**Pós-condições:** pessoa selecionada; se nova, importada.
**Efeitos colaterais:** importação de pessoas; criação/remoção de vínculos; nenhuma auditoria.
**Exceções:** Replicado indisponível: se há resultado local, continua silenciosamente; se não há, erro (`ManageLancamentos.php:176-185`).
**Evidência:** `ManageLancamentos.php`, `ReplicadoService.php`.
**Suficiente?** **Não.** O fluxo não define a autoridade dos dados, a atualização de vínculos, o escopo de unidade nem o que fazer com pessoa sem vínculo. O comportamento muda conforme a chave de busca.

### F-07 — Importação/atualização de pessoa e vínculos

**Ator:** sistema, disparado pela busca ou pela cota especial.
**Gatilho:** pessoa nova vinda do Replicado.
**Passos observáveis:**
1. `Pessoa::firstOrCreate` por `codigo_pessoa`; nome só é usado na criação (`ManageLancamentos.php:111-114`).
2. Para cada vínculo, `Vinculo::firstOrCreate` (`ManageLancamentos.php:117-127`).
3. Remove vínculos locais que não estão no Replicado (`ManageLancamentos.php:129-130`).
4. `CotaEspecialForm` faz importação semelhante dentro da validação do formulário (`app/Filament/Resources/CotaEspecials/Schemas/CotaEspecialForm.php:23-59`).
**Regras aplicadas:** RN-018 a RN-023, RN-049.
**Pós-condições:** pessoa e vínculos locais existem; cota pode ser calculada.
**Efeitos colaterais:** remoção destrutiva de vínculos; nenhuma transação no fluxo Livewire; nenhuma auditoria; nenhum registro de origem.
**Exceções:** falha de vínculo no Replicado é engolida (`ReplicadoService.php:232-234`); falha de banco pode deixar pessoa sem vínculos.
**Evidência:** `ManageLancamentos.php:105-133`, `CotaEspecialForm.php:23-59`, `ReplicadoService.php:212-237`.
**Suficiente?** **Não.** Não há regra de atualização para pessoa existente, nem de remoção de vínculo, nem de falha parcial. O fluxo de remoção só é alcançado em importações de pessoa nova, não no caminho normal de atualização.

### F-08 — Seleção de pessoa e exibição de cota, saldo e histórico

**Ator:** operador.
**Gatilho:** seleção de um resultado.
**Passos observáveis:**
1. `Pessoa::find` local (`ManageLancamentos.php:80`).
2. `$pessoa->saldo` chama `CotaService::calcularSaldo` (`app/Models/Pessoa.php:102-107`).
3. Exibe `cotaBase` (regular), `cotaEspecial` (soma), `vinculos` e `saldoAtual` (`ManageLancamentos.php:84-87`).
4. Histórico do mês paginado em 5 (`ManageLancamentos.php:299-305`).
**Regras aplicadas:** RN-001 a RN-012.
**Pós-condições:** dados exibidos.
**Efeitos colaterais:** nenhum.
**Exceções:** pessoa não encontrada localmente após seleção → alerta e limpeza (`ManageLancamentos.php:96-99`).
**Evidência:** `ManageLancamentos.php`, `manage-lancamentos.blade.php:92-108`.
**Suficiente?** **Não.** A tela mostra a cota base e a especial sem dizer qual é a efetiva; se a especial for menor, o operador vê dois números e um saldo que não corresponde à expectativa. Não mostra o motivo da cota, a origem dos créditos nem o saldo anterior.

### F-09 — Lançamento de débito

**Ator:** operador.
**Gatilho:** botão “Registrar Débito”.
**Pré-condições:** pessoa selecionada; sessão autenticada; `operar-sistema`.
**Passos observáveis:**
1. Abre modal, define tipo `1` (`ManageLancamentos.php:238-247`).
2. Valida `valorLancamento` como inteiro mínimo 1 (`ManageLancamentos.php:49-50`).
3. `CotaService::registrarDebitoOuCredito` cria `Lancamento` com `data = now()`, tipo, valor, pessoa e `auth()->id()` (`CotaService.php:25-38`).
4. Recarrega a pessoa, fecha o modal e mostra alerta (`ManageLancamentos.php:278-293`).
**Regras aplicadas:** RN-009 a RN-017.
**Pós-condições:** débito registrado; saldo recalculado.
**Efeitos colaterais:** auditoria de criação do lançamento (model Auditable); nenhuma notificação ao beneficiário.
**Exceções:** valor inválido; pessoa/tipo ausente; falha de banco.
**Evidência:** `ManageLancamentos.php`, `CotaService.php`, `manage-lancamentos.blade.php:219-281`.
**Suficiente?** **Não.** Não há verificação de saldo (por decisão), motivo, confirmação, recibo, limite, idempotência ou data retroativa. O fluxo não distingue consumo normal de ajuste.

### F-10 — Lançamento de crédito

**Ator:** operador.
**Gatilho:** botão “Registrar Crédito”.
**Pré-condições:** pessoa selecionada.
**Passos observáveis:** idênticos ao débito, com tipo `0` (`ManageLancamentos.php:238-247,278-283`).
**Regras aplicadas:** RN-010, RN-011, RN-015.
**Pós-condições:** crédito registrado; saldo recalculado.
**Efeitos colaterais:** auditoria de criação; nenhuma origem financeira.
**Exceções:** valor inválido.
**Evidência:** `ManageLancamentos.php`, `CotaService.php`.
**Suficiente?** **Não.** O sistema não sabe se o crédito é compra, bonificação, ajuste ou transferência; não há recibo, valor pago, autorização ou validade. O crédito expira no mês seguinte porque o cálculo só soma o mês corrente.

### F-11 — Cálculo de cota e saldo mensal

**Ator:** sistema.
**Gatilho:** seleção de pessoa, recálculo após lançamento, acesso ao atributo `saldo`.
**Passos observáveis:**
1. Se existe qualquer `CotaEspecial`, usa a soma de todas (`CotaService.php:53-61,96-109`).
2. Senão, pega os tipos de vínculo locais e o maior `Cota.valor` (`CotaService.php:79-90`).
3. Soma créditos do mês (`CotaService.php:133-142`).
4. Soma débitos do mês (`CotaService.php:116-126`).
5. `saldo = cota + créditos − débitos` (`CotaService.php:63-69`).
**Regras aplicadas:** RN-001 a RN-012.
**Pós-condições:** inteiro de saldo.
**Efeitos colaterais:** nenhum; cálculo em tempo real.
**Exceções:** sem vínculo → 0; sem cota para o vínculo → 0; cota especial 0 via banco → 0.
**Evidência:** `CotaService.php`, `Lancamento.php:73-77`.
**Suficiente?** **Parcialmente.** O cálculo é legível, mas as escolhas de composição (soma de especiais, máximo de vínculos, crédito mensal) não estão justificadas nem versionadas.

### F-12 — Virada de mês e temporalidade

**Ator:** sistema.
**Gatilho:** mudança de mês no relógio da aplicação.
**Passos observáveis:** `scopeMesAtual` filtra `whereYear`/`whereMonth` com `Carbon::now()` (`Lancamento.php:73-77`); a cota é recalculada e os lançamentos do mês anterior deixam de contar.
**Regras aplicadas:** RN-012, RN-013.
**Pós-condições:** saldo “renovado” para a cota base do novo mês; créditos antigos não contam; débitos antigos não contam.
**Efeitos colaterais:** nenhum evento, fechamento ou notificação.
**Exceções:** requisição que cruza a meia-noite pode misturar meses entre as duas consultas de `calcularSaldo`; lançamento às 23:59:59 e outro às 00:00:01 caem em meses diferentes.
**Evidência:** `Lancamento.php:73-77`, `CotaService.php:116-142`, `config/app.php:68`.
**Suficiente?** **Não.** Não há definição de fechamento, retroatividade, carryover, dívida ou corte; a “virada” é um efeito colateral de consulta.

### F-13 — Gestão de cotas regulares

**Ator:** Admin (na prática).
**Gatilho:** `/admin/cotas`.
**Passos observáveis:**
1. Lista, cria, edita e exclui `Cota` (`CotaResource.php`, `CotaForm.php`, `CotasTable.php`).
2. Campos: `tipo_vinculo` texto livre e `valor` numérico (`CotaForm.php:15-24`).
3. Exclusão individual e em massa (`CotasTable.php:39-45`).
**Regras aplicadas:** RN-005 a RN-008, RN-026, RN-028, RN-029.
**Pós-condições:** a política regular muda imediatamente para todos os cálculos.
**Efeitos colaterais:** auditoria de criação/edição/exclusão (`Cota` implementa Auditable).
**Exceções:** duplicidade permitida; valor negativo permitido; tipo de vínculo inexistente permitido.
**Evidência:** `Cota.php`, `CotaForm.php`, `CotasTable.php`, migration de `cotas`.
**Suficiente?** **Não.** Falta unicidade, normalização, validação contra vínculos conhecidos, vigência e valores oficiais. A tela permite configurar uma política que nunca será aplicada ou que será aplicada de forma imprevisível.

### F-14 — Gestão de cotas especiais

**Ator:** Admin (na prática).
**Gatilho:** `/admin/cota-especiais`.
**Passos observáveis:**
1. Formulário pede NUSP e valor (`CotaEspecialForm.php:15-69`).
2. Se a pessoa não existe localmente, busca no Replicado e importa dentro da validação (`CotaEspecialForm.php:23-49`).
3. Cria `CotaEspecial`; a tabela permite editar e excluir (`CotaEspecialsTable.php:39-47`).
**Regras aplicadas:** RN-002, RN-003, RN-004, RN-025, RN-027.
**Pós-condições:** a pessoa passa a ter cota especial; se já tinha, agora tem duas e o serviço soma.
**Efeitos colaterais:** importação de pessoa; auditoria de criação/edição/exclusão.
**Exceções:** NUSP inexistente; Replicado indisponível; valor mínimo 1.
**Evidência:** `CotaEspecialForm.php`, `CotaEspecialsTable.php`, `CotaService.php:96-109`, migration.
**Suficiente?** **Não.** Não há unicidade, vigência, motivo, autor explícito, aprovação ou revogação; a tela não mostra o nome antes de salvar; a validação pode criar pessoa mesmo se o valor for inválido.

### F-15 — Extrato

**Ator:** Admin (na prática; a policy também permite Operador, mas o painel o barra).
**Gatilho:** `/admin/extratos`.
**Passos observáveis:**
1. Lista todos os `Lancamento`, com pessoa, operador, valor, tipo e data (`ExtratosTable.php:19-62`).
2. Filtros por pessoa, operador e intervalo de datas (`ExtratosTable.php:63-99`).
3. Sem ações de criar, editar ou excluir (`ExtratosTable.php:100-108`).
**Regras aplicadas:** RN-038.
**Pós-condições:** visualização.
**Efeitos colaterais:** nenhum.
**Exceções:** nenhuma; a lista é global.
**Evidência:** `ExtratoResource.php`, `ExtratosTable.php`.
**Suficiente?** **Não.** Não há recorte “mês atual”, exportação, saldo corrente, composição da cota ou escopo por unidade. A documentação de arquitetura promete exportação (`docs/arquitetura/02:27`), que não existe.

### F-16 — Auditoria e logs

**Ator:** Admin.
**Gatilho:** `/admin/audits` ou relação de auditoria em Cota/User/Role/Permission.
**Passos observáveis:**
1. Lista `Audit` com usuário, evento, tipo, ID, data (`AuditResource.php:127-214`).
2. Permite visualizar detalhes; não permite criar, editar ou excluir (`AuditResource.php:231-249`).
3. `Cota`, `CotaEspecial`, `Lancamento` e `User` implementam Auditable; `Pessoa` e `Vinculo` não; `Role` e `Permission` têm observers próprios.
**Regras aplicadas:** RN-036, RN-037.
**Pós-condições:** registro de alterações selecionadas.
**Efeitos colaterais:** auditoria automática de modelo; sem auditoria de login, atribuição de papel, vínculo ou exclusão em cascata.
**Exceções:** auditoria pode ser desligada por `AUDITING_ENABLED`; usuário excluído aparece como “Sistema” (`AuditResource.php:47-53`).
**Evidência:** models, `AuditResource.php`, `RoleObserver.php`, `PermissionObserver.php`, `config/audit.php`.
**Suficiente?** **Não.** Não responde a “quem concedeu esta cota especial”, “quem mudou o papel deste usuário”, “quem fez login” ou “quem apagou este lançamento”.

### F-17 — Exclusões e ciclo de vida

**Ator:** Admin, operador (autoexclusão), tinker/banco.
**Gatilho:** exclusão de usuário pelo Filament ou pelo perfil; exclusão de pessoa via banco/tinker.
**Passos observáveis:**
1. `User` excluído → FK `lancamentos.usuario_id` em cascata (`database/migrations/2025_10_14_165401_create_lancamentos_table.php:24-28`).
2. `Pessoa` excluída → FK `lancamentos.codigo_pessoa`, `cota_especials.codigo_pessoa`, `vinculos.codigo_pessoa` em cascata (`...lancamentos...:20-23`, `...cota_especials...:17-20`, `...vinculos...:15-19`).
3. Autoexclusão pelo perfil (`resources/views/livewire/profile/delete-user-form.blade.php:14-22`).
**Regras aplicadas:** RN-016, RN-017, RN-035.
**Pós-condições:** registros apagados; histórico financeiro perdido.
**Efeitos colaterais:** nenhuma auditoria dos registros apagados em cascata; o `Audit` do usuário pode permanecer, mas o autor aparece como “Sistema”.
**Exceções:** `UserPolicy` impede autoexclusão pelo Filament, mas não pelo perfil; não há soft delete.
**Evidência:** migrations, profile, `UserPolicy`, `Pessoa`/`Vinculo`.
**Suficiente?** **Não.** O ciclo de vida destrói a própria evidência que o sistema deveria preservar.

### F-18 — Fluxos órfãos e implícitos

| Item | O que existe | Situação |
|---|---|---|
| `Mensagem` | Model vazio e tabela `mensagens` | Não usado; documentação de arquitetura diz que foi descartado |
| `CreateExtrato` / `EditExtrato` | Páginas Filament | Sem rotas; `ExtratoResource` só tem `index` |
| `ExtratoForm` | Schema vazio | Sem uso |
| `Pessoa::grupos()` | Relação `BelongsToMany` para `Grupo` | `Grupo` não existe; método não é usado |
| `PessoaResource` | Citado no AGENTE | Não existe |
| Notificações | Tabela `notifications`, `Notifiable`, painel com database notifications | Nenhuma notificação de negócio é enviada |
| Relatórios/exportação | Citados na documentação | Não existem |
| Fechamento/cobrança | Citados conceitualmente | Não existem |
| Login local por NUSP | Documentado no legado | Implementado por e-mail |
| Grupos/OU | Documentados no legado | Descartados, mas `User::grupos()` ainda referencia `Grupo` |

**Achado de suficiência:** o conjunto de fluxos permite entender o “caminho feliz” de lançamento, mas não permite entender a aplicação como sistema de cotas. Faltam fluxos de fechamento, correção, exceção, desligamento, auditoria de permissão, relatório e cobrança. Isso é um achado, não uma desculpa.

---

## 6. Catálogo de regras de negócio

> **Tipos:** E = explícita (documentada); I = implícita (decorre do comportamento); A = assumida (ninguém declarou, mas o sistema opera como se fosse verdade); X = ausente (deveria existir e não existe).

| ID | Enunciado | Tipo | Onde aparece | Evidência | Conflitos | Confiança |
|---|---|---|---|---|---|---|
| RN-001 | A cota base é a especial, se existir; senão o maior valor de cota regular entre os vínculos; senão 0. | E | Cálculo | `CotaService.php:51-90` | RN-002, RN-004 | Alta |
| RN-002 | Se houver mais de uma cota especial, o valor efetivo é a soma de todas. | I | Cálculo | `CotaService.php:96-109` | RN-001, docs “prioridade absoluta” | Alta |
| RN-003 | A cota especial substitui a cota regular, não soma a ela. | E | Cálculo | `CotaService.php:53-61` | RN-004 | Alta |
| RN-004 | Uma cota especial menor que a regular reduz a cota da pessoa. | I | Cálculo | `CotaService.php:57-61`; docs `03:11` diz “maior benefício” | RN-003, docs | Alta |
| RN-005 | Para cada tipo de vínculo, vale o maior `Cota.valor` cadastrado. | E | Cálculo | `CotaService.php:87`; `AGENTE.md:115` | RN-013, RN-026 | Alta |
| RN-006 | Cota regular só se aplica se o `tipo_vinculo` da cota for exatamente um dos vínculos locais. | I | Cálculo | `CotaService.php:81-89` | RN-014 | Alta |
| RN-007 | A comparação de `tipo_vinculo` não é normalizada (caixa/espaço) no código. | I | Cálculo/UI | `CotaService.php:87`; `CotaForm.php:15-18` | RN-014 | Média (depende da collation) |
| RN-008 | Pessoa sem vínculo ou sem cota correspondente tem cota base 0. | E | Cálculo | `CotaService.php:83-85,89` | RN-020 | Alta |
| RN-009 | Débito e crédito exigem inteiro maior ou igual a 1. | E | UI | `ManageLancamentos.php:49-50` | RN-014 | Alta |
| RN-010 | Débito não verifica saldo; saldo negativo é permitido. | E | Lançamento | `CotaService.php:25-38`; `AGENTE.md:131` | RN-008, RN-042 | Alta |
| RN-011 | Crédito só conta no mês corrente e não é transportado. | I | Cálculo | `CotaService.php:133-142`; `Lancamento.php:73-77` | RN-007, docs “compra” | Alta |
| RN-012 | Saldo = cota base + créditos do mês − débitos do mês. | E | Cálculo | `CotaService.php:63-69` | — | Alta |
| RN-013 | O “mês” é o mês civil de `lancamentos.data` no fuso da aplicação. | E/I | Cálculo | `Lancamento.php:73-77`; `config/app.php:68` | RN-009 | Alta |
| RN-014 | Todo lançamento usa `data = now()`; não há data retroativa na UI. | I | Lançamento | `CotaService.php:31-37` | RN-013 | Alta |
| RN-015 | O histórico é append-only na UI: não há editar, excluir ou estornar lançamento. | I | Lançamento/Extrato | `ExtratosTable.php:100-108`; `Lancamento.php:36-42` | RN-016, RN-017 | Alta |
| RN-016 | Excluir pessoa apaga lançamentos, cota especial e vínculos. | I | Schema | migrations de `lancamentos`, `cota_especials`, `vinculos` | RN-015 | Alta |
| RN-017 | Excluir usuário apaga os lançamentos que ele registrou. | I | Schema | `...lancamentos...:24-28` | RN-015, RN-036 | Alta |
| RN-018 | Pessoa e vínculos são importados do Replicado; a base local é preferencial para `codpes` já existente. | E/I | Busca | `ReplicadoService.php:77-91`; `ManageLancamentos.php:203-215` | RN-019 | Alta |
| RN-019 | Vínculos de pessoa já existente não são atualizados nos caminhos normais. | I | Busca | `ReplicadoService.php:80-90`; `ManageLancamentos.php:208-210` | RN-018 | Alta |
| RN-020 | A remoção de vínculo só ocorre quando uma pessoa nova é importada. | I | Importação | `ManageLancamentos.php:129-130` | RN-019 | Alta |
| RN-021 | Qualquer pessoa encontrada no Replicado pode ser importada, sem filtro de unidade. | I | Busca | `ReplicadoService.php:94-98,133-136,174-176` | RN-049 | Alta |
| RN-022 | A busca por nome filtra `nompes`, mas exibe `nomcnhpes` quando existir. | I | Busca | `ReplicadoService.php:174-176` | — | Alta |
| RN-023 | Busca por `codpes` exige vínculo não desativado; busca por e-mail/nome não exige. | I | Busca | `ReplicadoService.php:94-98,133-136,174-176` | RN-021 | Alta |
| RN-024 | Um único resultado é selecionado automaticamente; vários exigem escolha manual. | E | Busca | `ManageLancamentos.php:218-224` | — | Alta |
| RN-025 | Uma pessoa pode ter mais de uma cota especial; não há unicidade. | I | Schema | migration de `cota_especials` | RN-002 | Alta |
| RN-026 | Um `tipo_vinculo` pode ter mais de uma cota regular; não há unicidade. | I | Schema | migration de `cotas` | RN-005, RN-014 | Alta |
| RN-027 | Cota especial não tem vigência, motivo, autor explícito ou revogação. | X | Modelo/UI | `CotaEspecial.php:29-42`; `CotaEspecialForm.php:15-69` | ACH-006 | Alta |
| RN-028 | Cota regular não tem vigência nem versão. | X | Modelo/UI | `Cota.php:29-32`; `CotaForm.php:15-24` | ACH-005 | Alta |
| RN-029 | Alterações de cota afetam o cálculo atual e qualquer recomputação do passado. | I | Cálculo | `CotaService.php:79-109`; `Cota.php` | ACH-005 | Alta |
| RN-030 | Só `Admin`/`ADM` acessa o painel; `Operador` não acessa. | I | Middleware | `AdminPanelProvider.php:124`; `User.php:80-88` | RN-031, docs 04 | Alta |
| RN-031 | O operador acessa apenas a tela de lançamento, não a gestão de cotas nem o extrato. | I | Rotas/UI | `routes/web.php:19-25`; `navigation.blade.php:36-41` | docs 04 | Alta |
| RN-032 | A permissão de negócio para lançar é `operar-sistema`. | E | Rota/seeder | `routes/web.php:23`; `RoleSeeder.php:27-39` | RN-030 | Alta |
| RN-033 | Login por Senha Única auto-provisiona usuário, mas não atribui papel de negócio. | I | Auth | `SenhaunicaController.php:50-62`; `HasSenhaunica.php:160-174` | RN-030 | Alta |
| RN-034 | Registro público é aberto e atribui `usp_user`/`external_user`, sem acesso ao negócio. | I | Auth | `routes/auth.php:7-9`; `register.blade.php:108-131`; `RoleSeeder.php:30-31` | docs 04 | Alta |
| RN-035 | Qualquer autenticado pode excluir a própria conta. | I | Perfil | `profile/delete-user-form.blade.php:14-22` | RN-017 | Alta |
| RN-036 | Auditoria cobre `User`, `Cota`, `CotaEspecial`, `Lancamento` e CRUD de `Role`/`Permission`; não cobre `Pessoa`, `Vinculo`, atribuição de papel, login ou cascata. | I | Auditoria | models; `RoleObserver`; `PermissionObserver`; `config/audit.php` | RN-037 | Alta |
| RN-037 | Auditoria é visível só para `Admin`; não há exclusão pela UI, mas não há imutabilidade de banco nem retenção definida. | E/I | Auditoria | `AuditPolicy.php`; `AuditResource.php:231-249` | RN-036 | Alta |
| RN-038 | O extrato mostra todos os lançamentos, de todas as pessoas e operadores, sem escopo de unidade. | E | Extrato | `ExtratosTable.php:19-99` | RN-021 | Alta |
| RN-039 | Os valores de cota do seeder são “simulação” e podem virar política em produção. | I | Seeder/deploy | `CotaSeeder.php:18-40`; `docs/DEPLOY_PORTAINER.md:252-254` | ACH-019 | Alta |
| RN-040 | `SERVIDOR` e vários vínculos não têm cota por padrão e ficam com 0. | I | Seeder/cálculo | `CotaSeeder.php:19-40`; `CotaService.php:87-89` | README 15 | Alta |
| RN-041 | Uma mesma pessoa pode ser operador (`User`) e beneficiário (`Pessoa`) sem vínculo entre os registros. | I | Modelo | `User.php:47-52`; `Pessoa.php:53-56` | RN-015 | Alta |
| RN-042 | Não há fechamento, cobrança, bloqueio, notificação ou regularização de saldo negativo. | X | Ausente | busca no repositório | RN-010 | Alta |
| RN-043 | Não há idempotência: submissões repetidas criam lançamentos repetidos. | X | Ausente | `ManageLancamentos.php:264-293`; schema sem unique | RN-009 | Alta |
| RN-044 | O operador pode conceder crédito arbitrário, sem limite ou justificativa. | I | Lançamento | `ManageLancamentos.php:238-293` | RN-009 | Alta |
| RN-045 | O operador é registrado em cada lançamento, mas não há assinatura, recibo ou confirmação do beneficiário. | I | Lançamento | `CotaService.php:31-37` | RN-016 | Alta |
| RN-046 | `ADM` e `Admin` são aceitos para entrar no painel, mas as policies só aceitam `Admin` (e `Operador` no domínio). | I | Auth/policies | `User.php:85`; `AdminPanelProvider.php:124`; policies | RN-047 | Alta |
| RN-047 | O vocabulário efetivo dos seeders é `Admin`/`Operador`; README, AGENTE, docs e testes usam `ADM`/`OPR`. | I | Docs/seeders/testes | `RoleSeeder.php:34-35`; `README.md:42`; `AGENTE.md:230-231`; `AdminAccessTest.php:25-33` | RN-046 | Alta |
| RN-048 | O login local usa e-mail + senha; o legado (relatado) usava NUSP + senha. | I | Auth | `LoginForm.php:15-19`; `docs/analise/01:19-24` | ACH-021 | Alta |
| RN-049 | Não há restrição de unidade; qualquer pessoa da USP é elegível. | X | Busca | `ReplicadoService.php`; `REPLICADO_CODUNDCLG` só em docs/env | RN-021 | Alta |
| RN-050 | Cota regular aceita valor negativo; cota especial exige mínimo 1 na UI. | I | UI | `CotaForm.php:21-24`; `CotaEspecialForm.php:65-69` | RN-009 | Alta |

---

## 7. Análise crítica por eixo

### 7.1 Completude

Faltam regras para: pessoa sem vínculo, pessoa desligada/falecida/transferida, mudança de vínculo no meio do mês, múltiplos vínculos com cotas diferentes, múltiplas cotas especiais, cota especial zero, cota regular negativa, duplicidade de cota, duplicidade de lançamento, lançamento retroativo, fechamento, estorno, cobrança, notificação, aprovação de exceção, aprovação de crédito, último administrador, operador que também é beneficiário, falha parcial do Replicado, unidade organizacional e retenção de dados.

A ausência não é apenas “falta de código”: é falta de decisão. O sistema opera como se as respostas fossem óbvias, mas cada uma delas muda o resultado financeiro.

### 7.2 Consistência

Há contradições em cadeia:

- README usa `ADM`/`OPR`; seeders usam `Admin`/`Operador`; policies usam `Admin`/`Operador`; middleware aceita `ADM|Admin`; testes usam `ADM`/`OPR`. O mesmo usuário pode ser simultaneamente autorizado e negado dependendo da camada.
- O menu mostra “Painel de Admin” ao operador; o middleware o nega.
- A documentação diz que o operador gerencia cotas e vê extrato; o painel só permite Admin.
- A documentação diz que a especial tem “prioridade absoluta” e garante “maior benefício”; o código soma múltiplas especiais e permite especial menor que a regular.
- A documentação diz que o histórico é preservado; o schema apaga lançamentos em cascata.
- A documentação diz que todas as operações críticas são logadas; login, vínculo e atribuição de papel não são.
- A documentação da arquitetura diz que o extrato exporta; não há exportação.
- O termo de abertura é de um starter kit e exclui o negócio; o README é de um sistema em produção.

### 7.3 Determinismo e unicidade

Dado o mesmo estado do banco, o cálculo é determinístico: `max` e `sum` não dependem de ordem. Mas o **estado do banco não é único**:

- Várias cotas para o mesmo `tipo_vinculo`: o `max` escolhe a maior; editar a menor não tem efeito; excluir a maior muda a cota para a próxima.
- Várias cotas especiais: o `sum` soma; criar uma segunda especial não “substitui” a primeira.
- Vários usuários com o mesmo `codpes`: o login por Senha Única usa `firstOrNew` e pode escolher um; o outro fica órfão.
- Vários lançamentos iguais: não há chave de idempotência.

A invariante “a mesma pessoa e a mesma data produzem a mesma cota” só vale para o estado atual; não vale para a data histórica, porque a regra não é versionada.

### 7.4 Prioridade e composição

A composição real é:

```
se existe especial: base = soma(especiais)
senão: base = max(cotas dos vínculos)
saldo = base + créditos do mês − débitos do mês
```

Isso responde “a especial é absoluta?” com **não**: ela é um override, mas múltiplas especiais somam. Responde “créditos somam sobre a especial?” com **sim**, mas o crédito expira no mês. Responde “a cota regular máxima é a regra correta?” com **indeterminado**: é uma escolha, não uma necessidade.

Cenários que quebram:

- Docente com cota regular 1000 e especial 100 → recebe 100. A documentação promete “maior benefício”; o sistema reduz.
- Pessoa com duas especiais, 500 e 300 → recebe 800. Se a intenção era “uma exceção”, o segundo registro deveria substituir, não somar.
- Pessoa com vínculo `SERVIDOR` (sem cota) e `ALUNOGR` (500) → recebe 500. Se o vínculo principal fosse `SERVIDOR`, a regra “maior” não reflete a política.
- Pessoa com cota regular 0 e crédito 100 → pode consumir 100. É uma exceção informal sem motivo.
- Cota regular negativa via UI → base negativa para todos com aquele vínculo.

### 7.5 Temporalidade

O “mês” é o mês civil de `lancamentos.data` no fuso `America/Sao_Paulo`. O lançamento sempre usa `now()`; não há data retroativa. Isso tem consequências:

- Uma compra feita às 23:59 do dia 31 conta no mês antigo; outra às 00:01 conta no novo.
- Um consumo esquecido ontem não pode ser lançado em ontem; ele entra hoje e pode cair em outro mês.
- Não há fechamento; um lançamento retroativo via banco (ou importação) pode alterar um mês já “fechado” na cabeça das pessoas.
- Uma alteração de cota hoje muda a recomputação de qualquer mês passado, porque a cota não é versionada.
- Uma requisição que cruza a meia-noite pode calcular créditos de um mês e débitos de outro, pois `calcularSaldo` chama `Carbon::now()` em consultas separadas (`CotaService.php:63-64,116-142`).
- Créditos não usados desaparecem; débitos negativos não são transportados. O “saldo” de um mês não é um saldo contábil, é uma janela de consumo.

### 7.6 Integridade e ciclo de vida

- Excluir `User` apaga lançamentos; excluir `Pessoa` apaga tudo. Não há soft delete, tombstone ou anonimização.
- Autoexclusão de conta está disponível a qualquer autenticado.
- Vínculos não são atualizados para pessoa existente; a remoção só ocorre em importação de pessoa nova. Uma pessoa desligada pode manter a cota indefinidamente.
- Não há estado “inativo”, “bloqueado”, “falecido” ou “transferido”.
- Falha parcial do Replicado pode criar pessoa sem vínculos e cota 0.
- Se o Replicado cair, a busca por `codpes` de pessoa local retorna o local sem aviso; a busca por nome/e-mail com resultado local continua; a busca sem local falha. O comportamento é inconsistente e não documentado.
- Se o Replicado mudar o nome, a pessoa local não é atualizada nos caminhos normais.
- Se o Replicado mudar o e-mail, o `User` de Senha Única é atualizado, mas o `Pessoa` não; são bases separadas.

### 7.7 Autorização e segregação

- O Admin pode tudo: política, exceção, crédito, exclusão, auditoria. Não há separação entre quem define a política e quem opera.
- O Operador pode conceder crédito arbitrário. Se ele também for beneficiário (o modelo permite), pode creditar a si mesmo.
- O Operador não pode administrar cotas na prática, apesar da policy e da documentação.
- O Admin pode alterar a própria cota especial, o próprio crédito e a própria role; não há segundo aprovador.
- A atribuição de papel não é auditada; a remoção de papel também não.
- O `/loginas` do pacote Senha Única permite assumir identidade de outra pessoa; é protegido pelo gate `admin` do guard `senhaunica`, que é um segundo sistema de administração, e não é auditado.
- Não há proteção do último admin, nem contra autodemissão, nem contra exclusão do papel `Operador` (só `Admin` é protegido em `RolePolicy.php:47-52`).
- O `deleteAny` de `CotaPolicy`/`CotaEspecialPolicy`/`LancamentoPolicy` chama `hasRole('Admin', 'Operador')`; o segundo argumento é o guard no Spatie, não um segundo papel (`app/Policies/CotaPolicy.php:69-72`). Isso tende a negar exclusão em massa mesmo para Admin.

### 7.8 Rastreabilidade e auditoria

O que existe:

- `User`, `Cota`, `CotaEspecial`, `Lancamento` são Auditable; registram `created`, `updated`, `deleted`, com usuário, IP, URL e user agent.
- `Role` e `Permission` têm observers para CRUD da linha.
- `AuditResource` é somente leitura para Admin.

O que falta:

- `Pessoa` e `Vinculo` não são auditáveis: não se sabe quem importou, quem removeu vínculo ou quando o nome mudou.
- Atribuição/remoção de papel e de permissão em papel (pivot) não é auditada.
- Login, logout e falha de login não são auditados; o próprio pacote tem `//TODO: devemos gerar um log dos logins/logouts` (`vendor/uspdev/senhaunica-socialite/src/Http/Controllers/SenhaunicaController.php:58`).
- Exclusões em cascata não geram auditoria dos filhos, porque o banco apaga sem eventos Eloquent.
- Usuário excluído faz o autor aparecer como “Sistema” (`AuditResource.php:47-53`).
- Não há motivo/justificativa em cota, especial ou lançamento.
- Não há retenção definida; `threshold = 0` e nenhum prune (`config/audit.php:143`).
- Não há imutabilidade de banco; a UI impede exclusão, mas o banco não.
- Não é possível reconstruir o saldo de um mês passado com as regras da época usando o sistema; seria necessário um trabalho forense manual sobre os audits.

### 7.9 Invariantes e bordas

| Borda | Comportamento | Risco |
|---|---|---|
| Cota regular 0 | Base 0; pode receber crédito e consumir | Exceção informal sem motivo |
| Cota regular negativa | Base negativa | Saldo negativo imediato para todos |
| Cota especial 0 via banco | Base 0, bloqueia a regular | Efeito não intencional |
| Cota especial múltipla | Soma | Benefício maior ou menor que o esperado |
| Cota especial menor que regular | Reduz | Contradiz “maior benefício” |
| Pessoa sem vínculo | Cota 0; pode ser debitada | Consumo sem direito |
| Múltiplos vínculos | Maior cota | Pode overgrant |
| Vínculo duplicado | PK composta impede | OK |
| Cota duplicada | Permitida; `max` | Edição sem efeito |
| Lançamento duplicado | Permitido | Cobrança em dobro |
| Duplo clique | Sem bloqueio | Duplicidade |
| Valor enorme | Sem máximo | Erro de digitação vira dívida/crédito |
| Crédito sem débito | Permitido | Bonificação sem motivo |
| Débito sem saldo | Permitido | Dívida sem cobrança |
| Pessoa desligada | Cota antiga | Consumo indevido |
| Replicado parcialmente fora | Vínculo vazio | Cota 0 silenciosa |
| Requisição na meia-noite | Pode misturar meses | Saldo inconsistente |

### 7.10 Objetivo versus mecânica

O objetivo declarado é “controlar e auditar”. A mecânica atual:

- **Controla** o consumo apenas no sentido de registrar; não bloqueia, não cobra, não notifica.
- **Audita** apenas parte das entidades; não audita a identidade, o vínculo, a permissão nem a exclusão em cascata.
- **Não garante** que a cota seja a política institucional: o seeder é demo, o `SERVIDOR` não tem cota, não há filtro de unidade.
- **Cria incentivos perversos:** crédito mensal que expira, negativo que desaparece, operador que concede crédito a si, especial sem justificativa, cota máxima que premia manter vínculos.

### 7.11 Ambiguidade semântica

Os termos centrais não têm definição única. “Cota” é política, valor base e direito; “crédito” é compra e bonificação; “saldo” é disponibilidade e dívida; “mês” é calendário e ciclo; “prioridade” é override e soma; “histórico” é mês e tudo. Enquanto o dono do produto não fixar o vocabulário, qualquer implementação será defensável e contestável ao mesmo tempo.

### 7.12 Contradição documental

Ver seção 9. As contradições não são cosméticas: elas mudam quem acessa o quê, quanto cada pessoa recebe e o que se pode provar depois.

### 7.13 Fluxos ausentes

- Fechamento mensal e snapshot.
- Estorno/correção de lançamento.
- Lançamento retroativo com justificativa.
- Notificação ao beneficiário.
- Cobrança/regularização de saldo negativo.
- Relatório/exportação.
- Gestão de pessoa (editar, atualizar vínculo, inativar).
- Aprovação de cota especial.
- Aprovação/limite de crédito.
- Vigência de regra.
- Auditoria de login, papel e vínculo.
- Gestão de unidade.
- Recibo/comprovante.
- Reconciliação com o consumo físico.
- Retenção/descarte de dados.

### 7.14 Dependências externas

- O sistema assume que o Replicado está disponível e que `sitatl <> 'D'` significa “ativo”. Não há retry, cache (`REPLICADO_USAR_CACHE=false`), circuit breaker ou fallback definido.
- Assume que `tipvin` é o tipo de vínculo relevante e que `nomcaa` iniciado por “Docente” deve virar `DOCENTE`; não há contrato documentado.
- Assume que a busca por nome pode usar `nompes` e exibir `nomcnhpes`; não há regra de nome preferencial.
- Assume que qualquer pessoa da USP é elegível; não usa `REPLICADO_CODUNDCLG`.
- Assume que a Senha Única autentica quem deve operar; não atribui papel e não restringe unidade.
- Assume que o OAuth retorna e-mail único; se colidir com `users.email`, o login falha.
- Assume que o pacote Senha Única é a única fonte de permissões `senhaunica`; não há política de qual guard manda.

---

## 8. Registro consolidado de achados

> Formato: ID, título, categoria, severidade, regras/fluxos, fato, evidência, interpretação, impacto, risco de não decidir, pergunta, opções, recomendação, dependências, confiança.

### ACH-001 — Vocabulário de papéis e políticas de acesso incoerentes

**Categoria:** contradição.
**Severidade:** bloqueante.
**Regras/fluxos:** RN-030, RN-046, RN-047; F-01, F-02, F-05, F-17.
**Fato observado:** seeders criam `Admin`/`Operador` (`RoleSeeder.php:34-35`); README, AGENTE, docs e testes usam `ADM`/`OPR` (`README.md:42,188-196`; `AGENTE.md:230-231`; `docs/arquitetura/01:33`; `AdminAccessTest.php:25-33`); o README afirma que `make:filament-user` “já cria um usuário apto a logar no Filament” (`README.md:188`), mas o comando não atribui papel; o painel aceita `ADM|Admin` (`AdminPanelProvider.php:124`), mas as policies só aceitam `Admin` (e `Operador` no domínio). O `deleteAny` chama `hasRole('Admin', 'Operador')`, usando o segundo argumento como guard (`CotaPolicy.php:69-72`).
**Evidência:** arquivos acima.
**Interpretação:** não há um conjunto canônico de papéis; a autorização depende da camada e do documento.
**Impacto operacional:** seguir o README produz um admin que entra no painel e é negado em todos os recursos; um operador não entra no painel; a exclusão em massa pode ser negada indevidamente.
**Risco de não decidir:** qualquer correção de permissão pode criar ou remover acesso sem que se saiba qual é o desejado; auditoria de acesso fica impossível.
**Pergunta de decisão:** qual é o conjunto canônico de papéis e qual é a matriz de acesso por papel?
**Opções:**
- A: `Admin`/`Operador` canônicos; remover `ADM`/`OPR` e alinhar docs/testes — trade-off: quebra compatibilidade com instalações que já usam `ADM`.
- B: `ADM`/`OPR` canônicos; alinhar policies/seeders — trade-off: quebra o que já está semeado.
- C: manter aliases com mapeamento explícito — trade-off: aumenta complexidade e risco de novas divergências.
**Recomendação:** A, com migração explícita de aliases e uma única fonte de verdade. É a opção com menor ambiguidade futura.
**Dependências:** ACH-002, ACH-027, ACH-028.
**Confiança:** alta.

### ACH-002 — Operador não acessa a gestão de cotas nem o extrato

**Categoria:** contradição.
**Severidade:** bloqueante.
**Regras/fluxos:** RN-030, RN-031; F-05, F-13, F-14, F-15.
**Fato observado:** o middleware do painel exige `ADM|Admin` (`AdminPanelProvider.php:124`) e `User::canAccessPanel` também nega `Operador` (`User.php:80-88`); o menu mostra “Painel de Admin” para `Admin` e `Operador` (`navigation.blade.php:80-82`); a documentação diz que o operador gerencia cotas e vê o extrato (`docs/analise/04:31-35`); as policies de domínio incluem `Operador` (`CotaPolicy.php:15-71`).
**Evidência:** arquivos acima.
**Interpretação:** a policy e o menu prometem uma capacidade que o middleware nega. A documentação descreve um operador que não existe.
**Impacto operacional:** o operador clica no menu e recebe 403; a gráfica não consegue ajustar cota nem consultar extrato sem um Admin.
**Risco de não decidir:** a operação real depende de uma pessoa com papel Admin, mas ninguém sabe disso; o menu induz ao erro.
**Pergunta de decisão:** o operador deve administrar cotas e ver o extrato global, ou isso é exclusivo do Admin?
**Opções:**
- A: exclusivo do Admin; remover links e docs — trade-off: menos autonomia para a gráfica.
- B: operador gerencia cotas/extrato; ampliar o acesso do painel — trade-off: aumenta o poder do operador e o conflito de interesse.
- C: telas separadas para o operador com escopo limitado — trade-off: mais desenvolvimento e mais regra de escopo.
**Recomendação:** B ou C somente com segregação de funções; se não houver, A é mais defensável.
**Dependências:** ACH-001, ACH-015.
**Confiança:** alta.

### ACH-003 — Exclusão de usuário apaga lançamentos; autoexclusão permitida

**Categoria:** erro (violação de invariante).
**Severidade:** bloqueante.
**Regras/fluxos:** RN-017, RN-035; F-05, F-17.
**Fato observado:** `lancamentos.usuario_id` tem FK com `onDelete('cascade')` (`database/migrations/2025_10_14_165401_create_lancamentos_table.php:24-28`); o perfil permite ao próprio usuário excluir a conta (`profile/delete-user-form.blade.php:14-22`); `UserPolicy` permite Admin excluir outros usuários (`UserPolicy.php:44-52`).
**Evidência:** arquivos acima.
**Interpretação:** o livro-razão depende da existência do operador; apagar o operador apaga o livro.
**Impacto operacional:** um operador pode apagar o próprio histórico; um admin pode apagar o histórico de um operador demitido; saldos de beneficiários mudam porque lançamentos somem.
**Risco de não decidir:** impossível auditar ou cobrar; a exclusão pode ser usada para esconder erro ou fraude.
**Pergunta de decisão:** o histórico financeiro é imutável mesmo quando o operador deixa a instituição?
**Opções:**
- A: nunca excluir usuário com lançamentos; inativar — trade-off: exige estado inativo.
- B: soft delete/anonimização do operador, preservando o vínculo do lançamento — trade-off: mais complexidade.
- C: manter a cascata e aceitar a perda — trade-off: indefensável para auditoria.
**Recomendação:** A ou B; C não é defensável.
**Dependências:** ACH-004, ACH-018, ACH-020.
**Confiança:** alta.

### ACH-004 — Exclusão de pessoa apaga lançamentos, cota especial e vínculos

**Categoria:** erro (violação de invariante).
**Severidade:** bloqueante.
**Regras/fluxos:** RN-016; F-07, F-17.
**Fato observado:** FKs de `lancamentos`, `cota_especials` e `vinculos` para `pessoas` usam `onDelete('cascade')` (`...lancamentos...:20-23`; `...cota_especials...:17-20`; `...vinculos...:15-19`). Não há UI de exclusão de pessoa, mas o schema permite via tinker/banco.
**Evidência:** migrations.
**Interpretação:** a integridade referencial foi usada para “limpar” dados, não para preservar história.
**Impacto operacional:** qualquer limpeza de pessoa apaga o extrato e a cota especial; não há como responder a uma disputa.
**Risco de não decidir:** o primeiro suporte que “corrigir” uma pessoa pode destruir evidência.
**Pergunta de decisão:** uma pessoa pode ser excluída? Se sim, o que acontece com seu histórico?
**Opções:**
- A: nunca excluir; inativar/anonimizar — trade-off: exige estado e retenção.
- B: soft delete com histórico preservado — trade-off: complexidade.
- C: exclusão física com histórico apagado — trade-off: indefensável.
**Recomendação:** A ou B.
**Dependências:** ACH-003, ACH-020.
**Confiança:** alta.

### ACH-005 — Não há fechamento mensal nem versionamento; o passado não é reconstruível

**Categoria:** lacuna.
**Severidade:** bloqueante.
**Regras/fluxos:** RN-028, RN-029; F-11, F-12, F-13, F-14.
**Fato observado:** o saldo é sempre recalculado com a cota atual (`CotaService.php:51-70`); `Cota` e `CotaEspecial` não têm vigência, versão ou snapshot (`Cota.php:29-32`; `CotaEspecial.php:29-42`); não há tabela de fechamento.
**Evidência:** arquivos acima.
**Interpretação:** “histórico” é apenas a lista de lançamentos; a regra que valia na época não é preservada.
**Impacto operacional:** uma alteração de cota hoje muda a leitura de um mês passado; não se consegue provar quanto uma pessoa tinha direito em uma data.
**Risco de não decidir:** toda disputa retroativa é insolúvel; a auditoria não serve para prestação de contas.
**Pergunta de decisão:** o saldo de um mês fechado deve ser imutável e reconstruível?
**Opções:**
- A: snapshot mensal por pessoa (cota, créditos, débitos, saldo) — trade-off: mais dados e processo de fechamento.
- B: versionar regras com vigência e calcular por data — trade-off: mais complexo, mas preserva o passado.
- C: aceitar que o passado é recalculado — trade-off: não é auditável.
**Recomendação:** A + B, se o objetivo é auditoria; A sozinho já resolve a prova.
**Dependências:** ACH-006, ACH-007, ACH-009.
**Confiança:** alta.

### ACH-006 — Cota especial múltipla, sem vigência/motivo/autor e pode reduzir

**Categoria:** ambiguidade/conflito.
**Severidade:** bloqueante.
**Regras/fluxos:** RN-002, RN-003, RN-004, RN-025, RN-027; F-14.
**Fato observado:** a migration não tem unique por `codigo_pessoa`; o formulário permite criar quantas quiser; o serviço soma (`CotaService.php:96-109`); a documentação diz “prioridade absoluta” e “maior benefício” (`docs/analise/03:11`); uma especial menor que a regular reduz a cota; não há motivo/validade nem autor de negócio (apenas o usuário do log de auditoria).
**Evidência:** migration, `CotaEspecialForm.php`, `CotaService.php`, docs.
**Interpretação:** o sistema não sabe se a especial é um valor único, um acréscimo ou um piso.
**Impacto operacional:** uma segunda especial pode dobrar o benefício; uma especial de 100 sobre uma regular de 1000 reduz o benefício; não há justificativa para auditoria.
**Risco de não decidir:** qualquer concessão é contestável; o erro de digitação vira política.
**Pergunta de decisão:** a cota especial é única, substitui a regular, é piso (`max(regular, especial)`) ou soma? Tem vigência e motivo?
**Opções:**
- A: única, override, com vigência/motivo/autor — trade-off: exige unicidade e workflow.
- B: piso (`max`) — trade-off: nunca reduz, mas pode custar mais.
- C: adicional — trade-off: pode somar benefícios não intencionais.
**Recomendação:** A, com `max` como salvaguarda se a intenção é “nunca reduzir”.
**Dependências:** ACH-005, ACH-015.
**Confiança:** alta.

### ACH-007 — Crédito expira e não tem origem financeira definida

**Categoria:** ambiguidade/risco.
**Severidade:** bloqueante.
**Regras/fluxos:** RN-011, RN-044; F-10, F-12.
**Fato observado:** `getCreditos` soma apenas o mês corrente (`CotaService.php:133-142`); a documentação fala em “compra de créditos adicionais” (`docs/analise/04:28`); não há valor pago, recibo, validade ou tipo de crédito.
**Evidência:** `CotaService.php`, docs.
**Interpretação:** “crédito” pode ser compra ou bonificação; o sistema trata os dois igualmente e apaga o saldo na virada.
**Impacto operacional:** se for compra, o usuário perde o que pagou; se for bonificação, a regra de expiração precisa ser declarada.
**Risco de não decidir:** risco financeiro e de reclamação; impossível conciliar caixa.
**Pergunta de decisão:** crédito é compra, bonificação, ajuste ou transferência? Expira? Tem recibo?
**Opções:**
- A: crédito permanente (saldo acumulado) — trade-off: muda o modelo mensal.
- B: crédito mensal, com tipo “compra” e “benefício” — trade-off: mais campos e regras.
- C: crédito sempre mensal e não reembolsável — trade-off: exige comunicação explícita.
**Recomendação:** B se houver compra; C se for só benefício; A se o objetivo é saldo contábil.
**Dependências:** ACH-005, ACH-008, ACH-016.
**Confiança:** alta.

### ACH-008 — Saldo negativo sem política de dívida/cobrança

**Categoria:** lacuna/risco.
**Severidade:** bloqueante.
**Regras/fluxos:** RN-010, RN-042; F-09, F-12.
**Fato observado:** não há verificação de saldo no lançamento (`CotaService.php:25-38`); a documentação diz que o negativo é permitido e que o controle é administrativo (`AGENTE.md:131`; `docs/analise/03:43`); não há cobrança, bloqueio, notificação ou regularização.
**Evidência:** código e docs.
**Interpretação:** o negativo é ao mesmo tempo “dívida” e “esquecido na virada”; não há consequência.
**Impacto operacional:** consumo excedente sem cobrança; no mês seguinte, a pessoa recebe a cota cheia e a dívida desaparece.
**Risco de não decidir:** perda financeira e incentivo ao excesso.
**Pergunta de decisão:** saldo negativo é dívida, tolerância, erro ou estado proibido?
**Opções:**
- A: bloquear no limite (não permitir negativo) — trade-off: pode travar atendimento legítimo.
- B: permitir com dívida formal e cobrança — trade-off: exige processo financeiro.
- C: permitir como tolerância e não cobrar — trade-off: custo assumido.
**Recomendação:** B se a instituição cobra; C se a tolerância é política; A se o objetivo é controle rígido.
**Dependências:** ACH-007, ACH-009, ACH-020.
**Confiança:** alta.

### ACH-009 — “Mês” e retroatividade não definidos

**Categoria:** lacuna.
**Severidade:** bloqueante.
**Regras/fluxos:** RN-013, RN-014; F-11, F-12.
**Fato observado:** `scopeMesAtual` usa `Carbon::now()` e `whereYear/whereMonth` (`Lancamento.php:73-77`); o lançamento usa `now()` (`CotaService.php:31-37`); não há campo de data editável nem fechamento.
**Evidência:** código.
**Interpretação:** o “mês” é o mês de criação, não o mês do consumo real.
**Impacto operacional:** consumo esquecido não pode ser alocado no dia/mês correto; a virada pode distorcer o saldo; não há fechamento.
**Risco de não decidir:** disputas de fronteira de mês; impossibilidade de corrigir erro operacional.
**Pergunta de decisão:** qual é o período de apuração e o que é permitido lançar retroativamente?
**Opções:**
- A: mês civil, sem retroativo — trade-off: simples, mas distorce consumo real.
- B: mês civil, com retroativo justificado e auditado — trade-off: mais controle.
- C: ciclo de fechamento (ex.: dia 25) — trade-off: mais complexo, mas pode refletir a operação.
**Recomendação:** B se a operação erra; A se o registro é sempre em tempo real.
**Dependências:** ACH-005, ACH-008.
**Confiança:** alta.

### ACH-010 — Vínculos de pessoa existente não são atualizados; sem estado inativo

**Categoria:** fragilidade/erro.
**Severidade:** alta.
**Regras/fluxos:** RN-018, RN-019, RN-020; F-06, F-07.
**Fato observado:** `buscarPessoaPorCodpes` retorna o local sem consultar o Replicado (`ReplicadoService.php:77-91`); no merge, resultado do Replicado com `codpes` local é ignorado (`ManageLancamentos.php:203-215`); a remoção de vínculo só ocorre em `importarPessoa` para pessoa nova (`ManageLancamentos.php:129-130`); não há estado ativo/inativo.
**Evidência:** arquivos acima.
**Interpretação:** o dado local é um retrato permanente; o Replicado não é autoridade depois da primeira importação.
**Impacto operacional:** pessoa que perdeu vínculo continua com a cota antiga; pessoa que mudou de vínculo continua com a cota antiga; o sistema não sabe que ela está inativa.
**Risco de não decidir:** consumo indevido por ex-alunos/servidores; cota errada para quem mudou de categoria.
**Pergunta de decisão:** quem é a autoridade sobre o vínculo e com qual frequência o sistema deve atualizá-lo?
**Opções:**
- A: Replicado é autoridade; atualizar a cada busca/consulta — trade-off: dependência e latência.
- B: sincronização periódica com TTL — trade-off: dado pode ficar defasado dentro do TTL.
- C: local é autoridade; atualização manual — trade-off: controle, mas risco de esquecimento.
**Recomendação:** A com cache curto e botão de atualização; se indisponível, usar o último dado com aviso.
**Dependências:** ACH-011, ACH-020.
**Confiança:** alta.

### ACH-011 — Falha parcial do Replicado pode gerar cota zero silenciosa

**Categoria:** fragilidade.
**Severidade:** alta.
**Regras/fluxos:** RN-018, RN-019; F-06, F-07.
**Fato observado:** `buscarVinculos` captura exceção, loga e retorna `[]` (`ReplicadoService.php:232-234`); `importarPessoa` cria pessoa e não cria vínculos (`ManageLancamentos.php:117-127`); `getCotaBase` retorna 0 (`CotaService.php:83-85`).
**Evidência:** arquivos acima.
**Interpretação:** uma falha parcial é tratada como “pessoa sem vínculo”, que é indistinguível de um dado real.
**Impacto operacional:** pessoa com cota 0 pode ser debitada ou receber crédito; o operador não vê que a fonte falhou.
**Risco de não decidir:** erros silenciosos de cota; confiança no cálculo é perdida.
**Pergunta de decisão:** quando o Replicado falha parcialmente, o sistema deve bloquear, usar o último dado ou marcar como “desconhecido”?
**Opções:**
- A: falhar fechado (não operar) — trade-off: indisponibilidade.
- B: usar último dado local com aviso — trade-off: pode estar obsoleto.
- C: criar pessoa com estado “vínculo desconhecido” e não calcular cota — trade-off: mais estados.
**Recomendação:** B com aviso e registro; C se a operação precisa continuar.
**Dependências:** ACH-010, ACH-020.
**Confiança:** alta.

### ACH-012 — Falta escopo de unidade; qualquer pessoa da USP é elegível

**Categoria:** lacuna.
**Severidade:** alta.
**Regras/fluxos:** RN-021, RN-049; F-06, F-07.
**Fato observado:** as queries de pessoa/vínculo não usam `codundclg` (`ReplicadoService.php:94-98,133-136,174-176,215-216`); `REPLICADO_CODUNDCLG` existe no `.env.example` e no compose, mas não é referenciado no código.
**Evidência:** busca no repositório.
**Interpretação:** o sistema é da USP, não do IME, no que se refere a elegibilidade.
**Impacto operacional:** qualquer pessoa de qualquer unidade pode receber cota/crédito; a instituição paga por consumo de fora.
**Risco de não decidir:** custo e escopo indefinidos; impossível prestar contas por unidade.
**Pergunta de decisão:** a elegibilidade é restrita ao IME (codundclg 45) ou a toda a USP?
**Opções:**
- A: restringir ao IME — trade-off: exclui convênios.
- B: USP inteira — trade-off: custo maior.
- C: IME + lista de exceções — trade-off: mais regra.
**Recomendação:** A com lista explícita de exceções, se houver.
**Dependências:** ACH-010.
**Confiança:** alta.

### ACH-013 — Composição da cota regular é decisão não documentada

**Categoria:** ambiguidade/decisão não documentada.
**Severidade:** alta.
**Regras/fluxos:** RN-005, RN-006; F-11.
**Fato observado:** o código usa `max('valor')` entre os vínculos (`CotaService.php:87`); a documentação diz “maior valor” e “maior benefício” (`AGENTE.md:115`; `docs/analise/03:11,29-30`), mas não justifica por que não soma, último vínculo ou vínculo principal.
**Evidência:** código e docs.
**Interpretação:** “maior” é uma escolha de implementação, não uma regra de negócio validada.
**Impacto operacional:** uma pessoa com vínculo docente e discente recebe o maior; se o vínculo de maior cota é o que está terminando, o benefício pode ser indevido.
**Risco de não decidir:** a política pode conceder mais ou menos do que a instituição quer.
**Pergunta de decisão:** qual vínculo define a cota quando a pessoa tem vários?
**Opções:**
- A: maior — trade-off: generoso, pode overgrant.
- B: soma — trade-off: pode dobrar benefício.
- C: vínculo principal/último — trade-off: exige definir principal.
**Recomendação:** definir o vínculo elegível (ex.: o de maior prioridade institucional), não simplesmente o maior valor.
**Dependências:** ACH-010, ACH-012.
**Confiança:** alta.

### ACH-014 — Cotas regulares duplicadas, sem normalização e com valores inválidos

**Categoria:** fragilidade.
**Severidade:** alta.
**Regras/fluxos:** RN-005, RN-006, RN-007, RN-026, RN-050; F-13.
**Fato observado:** `cotas.tipo_vinculo` não é único (`...cotas...:14-19`); o formulário aceita texto livre e `valor` numérico sem mínimo (`CotaForm.php:15-24`); não há validação contra a lista de vínculos; a comparação é feita por `whereIn` (`CotaService.php:87`).
**Evidência:** migration, form, service.
**Interpretação:** é possível cadastrar “DOCENTE”, “docente”, “Docente”, duplicatas e valores negativos; o efeito depende da collation e do `max`.
**Impacto operacional:** cota silenciosamente 0 por typo; edição de uma linha duplicada sem efeito; valor negativo aplicado a todos.
**Risco de não decidir:** a política fica impossível de auditar e de explicar.
**Pergunta de decisão:** quais são os tipos de vínculo válidos e qual é a regra de unicidade/normalização?
**Opções:**
- A: lista canônica + unique + normalização — trade-off: exige migração.
- B: texto livre com validação contra o Replicado — trade-off: dependência externa.
- C: manter livre e aceitar o risco — trade-off: indefensável.
**Recomendação:** A.
**Dependências:** ACH-012, ACH-019.
**Confiança:** alta.

### ACH-015 — Operador concede crédito arbitrário e pode ser beneficiário

**Categoria:** risco/conflito de interesse.
**Severidade:** alta.
**Regras/fluxos:** RN-041, RN-044; F-09, F-10.
**Fato observado:** `registrarDebitoOuCredito` aceita qualquer valor e qualquer pessoa (`CotaService.php:25-38`); não há limite, aprovação ou motivo; `User` e `Pessoa` são tabelas separadas sem vínculo (`User.php:47-52`; `Pessoa.php:53-56`), então um operador pode buscar o próprio NUSP e creditar-se.
**Evidência:** código.
**Interpretação:** não há segregação entre quem concede e quem recebe.
**Impacto operacional:** crédito indevido, autobenefício, perda de controle.
**Risco de não decidir:** fraude e impossibilidade de auditoria.
**Pergunta de decisão:** quem pode conceder crédito, com qual limite e qual aprovação? Operador pode lançar para si?
**Opções:**
- A: somente Admin concede; operador só debita — trade-off: menos autonomia.
- B: operador concede até um limite, com motivo — trade-off: mais regra.
- C: operador concede livremente — trade-off: risco atual.
**Recomendação:** A ou B com trilha de aprovação.
**Dependências:** ACH-001, ACH-002, ACH-016.
**Confiança:** alta.

### ACH-016 — Lançamentos sem motivo, comprovante ou origem

**Categoria:** lacuna.
**Severidade:** alta.
**Regras/fluxos:** RN-045; F-09, F-10.
**Fato observado:** `Lancamento` só tem `data`, `tipo_lancamento`, `valor`, `codigo_pessoa`, `usuario_id` (`Lancamento.php:36-42`); não há motivo, observação, referência de pagamento, número de recibo ou assinatura.
**Evidência:** model, migration, UI.
**Interpretação:** o sistema registra “quanto”, mas não “por quê” nem “com base em quê”.
**Impacto operacional:** disputa do beneficiário não pode ser respondida; crédito de compra não pode ser conciliado com caixa.
**Risco de não decidir:** toda correção vira discussão informal.
**Pergunta de decisão:** quais campos são obrigatórios em cada tipo de lançamento?
**Opções:**
- A: motivo obrigatório em crédito e ajuste — trade-off: mais digitação.
- B: motivo opcional + referência para compra — trade-off: depende de disciplina.
- C: manter como está — trade-off: não auditável.
**Recomendação:** A para crédito/ajuste; B para débito.
**Dependências:** ACH-007, ACH-015.
**Confiança:** alta.

---

### ACH-017 — Sem idempotência; duplo clique duplica lançamento

**Categoria:** fragilidade.
**Severidade:** alta.
**Regras/fluxos:** RN-043; F-09, F-10.
**Fato observado:** não há chave de idempotência, unique ou bloqueio de submit; o botão de salvar do modal não é desabilitado durante o processamento (`manage-lancamentos.blade.php:271-276`); o serviço sempre cria (`CotaService.php:31-37`).
**Evidência:** UI e service.
**Interpretação:** dois cliques ou um reenvio criam dois lançamentos.
**Impacto operacional:** cobrança em dobro ou crédito em dobro.
**Risco de não decidir:** erros recorrentes no balcão.
**Pergunta de decisão:** como o sistema deve deduplicar um lançamento?
**Opções:**
- A: chave de idempotência por requisição — trade-off: mais técnica.
- B: confirmação e bloqueio de UI — trade-off: simples, mas não cobre reenvio.
- C: permitir duplicidade e corrigir depois — trade-off: exige processo de estorno.
**Recomendação:** A + B.
**Dependências:** ACH-009, ACH-016.
**Confiança:** alta.

### ACH-018 — Lacunas de auditoria

**Categoria:** lacuna.
**Severidade:** alta.
**Regras/fluxos:** RN-036, RN-037; F-16, F-17.
**Fato observado:** `Pessoa`/`Vinculo` não são Auditable; atribuição de papel via relação não gera `updated` no `User`; `Role`/`Permission` só auditam a linha, não o pivot; login/logout não são auditados; cascatas não geram eventos; usuário excluído vira “Sistema” (`AuditResource.php:47-53`).
**Evidência:** models, observers, controller do pacote.
**Interpretação:** a auditoria cobre o “quanto”, mas não o “quem autorizou”, “quem mudou o vínculo” ou “quem entrou”.
**Impacto operacional:** não se responde a uma disputa completa; não se detecta abuso de permissão.
**Risco de não decidir:** a auditoria existente dá falsa sensação de cobertura.
**Pergunta de decisão:** o que precisa ser reconstruível em uma disputa e por quanto tempo?
**Opções:**
- A: auditar todas as entidades e eventos de auth/permissão — trade-off: volume e custo.
- B: auditar só o financeiro e o vínculo — trade-off: menos cobertura.
- C: manter o atual — trade-off: insuficiente.
**Recomendação:** A para financeiro, vínculo e permissão; retenção explícita.
**Dependências:** ACH-003, ACH-004, ACH-005, ACH-028.
**Confiança:** alta.

### ACH-019 — Seeders de demonstração e credenciais padrão viram política/operação

**Categoria:** risco/decisão não documentada.
**Severidade:** alta.
**Regras/fluxos:** RN-039, RN-040; F-13.
**Fato observado:** `CotaSeeder` declara “Simulação 1..4” e grava 500/100/1000/750 (`CotaSeeder.php:18-40`); `SERVIDOR` não tem cota; `DatabaseSeeder` cria `test@example.com/password` (`DatabaseSeeder.php:18-23`); `FilamentAdminSeeder` cria `admin@usp.br/password` (`FilamentAdminSeeder.php:29-43`); README manda `migrate --seed` (`README.md:87,114`); deploy manda rodar `CotaSeeder` (`docs/DEPLOY_PORTAINER.md:252-254`).
**Evidência:** arquivos acima.
**Interpretação:** valores de teste são tratados como política; credenciais padrão podem existir em produção se o README for seguido.
**Impacto operacional:** cotas institucionais arbitrárias; funcionários com 0; acesso default.
**Risco de não decidir:** política de cota sem origem; risco de acesso não autorizado.
**Pergunta de decisão:** qual é a tabela oficial de cotas por vínculo e qual é a origem de cada valor?
**Opções:**
- A: substituir o seeder por valores aprovados — trade-off: exige decisão institucional.
- B: manter demo e documentar que não é política — trade-off: não serve para produção.
- C: remover o seeder de produção — trade-off: exige carga manual.
**Recomendação:** A; C para as credenciais padrão.
**Dependências:** ACH-012, ACH-014.
**Confiança:** alta.

### ACH-020 — Sem estado ativo/inativo; pessoa sem vínculo pode consumir

**Categoria:** lacuna.
**Severidade:** alta.
**Regras/fluxos:** RN-008, RN-010, RN-020; F-07, F-09.
**Fato observado:** `Pessoa` só tem `codigo_pessoa` e `nome_pessoa` (`Pessoa.php:53-56`); `Vinculo` só tem tipo; não há status; `getCotaBase` retorna 0 e o lançamento não bloqueia (`CotaService.php:25-38,83-85`).
**Evidência:** models, service.
**Interpretação:** “sem cota” e “inelegível” são a mesma coisa.
**Impacto operacional:** pessoa desligada, falecida, transferida ou sem vínculo pode ser debitada/creditada.
**Risco de não decidir:** consumo indevido e cobrança impossível.
**Pergunta de decisão:** quais estados de beneficiário existem e o que cada um permite?
**Opções:**
- A: `ativo`, `inativo`, `bloqueado`, `desconhecido` — trade-off: mais complexidade.
- B: apenas ativo/inativo — trade-off: mais simples.
- C: manter cota 0 como bloqueio implícito — trade-off: não bloqueia.
**Recomendação:** B com bloqueio de lançamento para inativo, se o objetivo é controlar.
**Dependências:** ACH-010, ACH-011.
**Confiança:** alta.

### ACH-021 — Login local por e-mail e senha manual divergem do legado

**Categoria:** contradição.
**Severidade:** média.
**Regras/fluxos:** RN-048; F-02, F-05.
**Fato observado:** o login local usa e-mail (`LoginForm.php:15-19`); o legado (relatado) usa NUSP + senha (`docs/analise/01:19-24`); o Admin digita a senha no formulário (`UserForm.php:32-37`); o legado gerava senha aleatória e a exibia (`docs/analise/01:112-115`).
**Evidência:** código e docs.
**Interpretação:** a migração mudou a identidade de login e o processo de provisionamento sem registrar a decisão.
**Impacto operacional:** operadores podem não saber o e-mail; o Admin precisa inventar/transmitir senha; a recuperação é por e-mail.
**Risco de não decidir:** suporte e onboarding inconsistentes.
**Pergunta de decisão:** o login local deve ser por NUSP ou e-mail? Quem define a senha inicial?
**Opções:**
- A: manter e-mail — trade-off: simples, mas depende de e-mail.
- B: voltar a NUSP — trade-off: mais próximo do legado.
- C: permitir ambos — trade-off: mais complexo.
**Recomendação:** B se os operadores são identificados por NUSP; A se o e-mail é o identificador institucional.
**Dependências:** ACH-022.
**Confiança:** alta.

### ACH-022 — Registro público órfão e risco de colisão de identidade

**Categoria:** lacuna/risco.
**Severidade:** média.
**Regras/fluxos:** RN-034; F-03.
**Fato observado:** `/register` é público (`routes/auth.php:7-9`); cria `usp_user`/`external_user` sem acesso ao negócio (`RoleSeeder.php:30-31`); o login local redireciona para `/lancamento` e pode dar 403 (`login.blade.php:25`); `users.codpes` não é único (`2025_04_11_133701_update_senhaunica_users_table.php:17-20`); Senha Única usa `firstOrNew` por `codpes` (`SenhaunicaController.php:50-55`).
**Evidência:** rotas, seeder, migration, controller.
**Interpretação:** o registro é um resíduo do starter kit; não serve ao domínio e cria identidades concorrentes.
**Impacto operacional:** usuários sem função; colisão de e-mail/codpes; suporte.
**Risco de não decidir:** base de usuários poluída e identidade ambígua.
**Pergunta de decisão:** o registro público deve existir? Quem pode ser operador?
**Opções:**
- A: fechar o registro; só Admin cria operador — trade-off: menos autoatendimento.
- B: manter registro com aprovação — trade-off: workflow.
- C: manter aberto — trade-off: risco atual.
**Recomendação:** A.
**Dependências:** ACH-001, ACH-021.
**Confiança:** alta.

### ACH-023 — UI não mostra a cota efetiva

**Categoria:** ambiguidade.
**Severidade:** média.
**Regras/fluxos:** RN-001, RN-003, RN-004; F-08.
**Fato observado:** a tela mostra “Cota Base”, “Cota Especial” e “Saldo” (`manage-lancamentos.blade.php:103-107`); não mostra qual delas é a efetiva; a especial exibida é a soma (`CotaService.php:108`).
**Evidência:** blade e service.
**Interpretação:** o operador vê dois números e um saldo que pode não corresponder à expectativa.
**Impacto operacional:** leitura errada da cota; discussão no balcão.
**Risco de não decidir:** erros de atendimento.
**Pergunta de decisão:** como a cota efetiva e sua composição devem ser exibidas?
**Opções:**
- A: mostrar “cota do mês” + composição — trade-off: mais informação.
- B: mostrar só a efetiva — trade-off: menos transparência.
- C: manter — trade-off: confuso.
**Recomendação:** A.
**Dependências:** ACH-006.
**Confiança:** alta.

### ACH-024 — Extrato sem recorte mensal, exportação ou saldo corrente

**Categoria:** lacuna/contradição.
**Severidade:** média.
**Regras/fluxos:** RN-038; F-15.
**Fato observado:** o extrato lista tudo com filtros de data (`ExtratosTable.php:19-99`); não há botão “mês atual”, exportação ou saldo (`ExtratosTable.php:100-108`); a arquitetura promete exportação (`docs/arquitetura/02:27`); o legado tinha dois botões (`docs/analise/01:131-134`).
**Evidência:** código e docs.
**Interpretação:** a funcionalidade prometida não foi entregue.
**Impacto operacional:** relatórios manuais; auditoria lenta.
**Risco de não decidir:** a gestão não consegue prestar contas.
**Pergunta de decisão:** quais relatórios e exportações são obrigatórios e para quem?
**Opções:**
- A: recorte mensal + CSV/PDF — trade-off: desenvolvimento.
- B: só filtro — trade-off: atual.
- C: relatório consolidado por pessoa/unidade — trade-off: mais complexo.
**Recomendação:** A + C, se houver prestação de contas.
**Dependências:** ACH-005, ACH-012.
**Confiança:** alta.

### ACH-025 — Validação da cota especial importa pessoa como efeito colateral

**Categoria:** fragilidade.
**Severidade:** média.
**Regras/fluxos:** RN-018, RN-027; F-07, F-14.
**Fato observado:** a regra de validação do NUSP busca no Replicado e cria `Pessoa`/`Vinculo` dentro de `DB::transaction` (`CotaEspecialForm.php:23-49`); se a validação de `valor` falhar depois, a pessoa permanece.
**Evidência:** form.
**Interpretação:** validar tem efeito de escrita.
**Impacto operacional:** pessoas órfãs; importações não intencionais.
**Risco de não decidir:** base de pessoas inconsistente.
**Pergunta de decisão:** a importação deve ocorrer na validação ou somente ao salvar?
**Opções:**
- A: só ao salvar — trade-off: validação mais complexa.
- B: manter — trade-off: efeito colateral.
- C: pré-visualizar e confirmar — trade-off: mais UX.
**Recomendação:** A.
**Dependências:** ACH-010, ACH-014.
**Confiança:** alta.

### ACH-026 — `users.codpes` não é único

**Categoria:** fragilidade.
**Severidade:** média.
**Regras/fluxos:** RN-033; F-01, F-05.
**Fato observado:** a migration adiciona `codpes` nullable sem unique (`2025_04_11_133701_update_senhaunica_users_table.php:17-20`); o formulário de usuário permite `codpes` (`UserForm.php:26-29`); a Senha Única busca por `codpes` (`SenhaunicaController.php:50`).
**Evidência:** migration, form, controller.
**Interpretação:** pode haver dois operadores com o mesmo NUSP; o login escolhe um.
**Impacto operacional:** papel/permissão no usuário errado; perda de acesso.
**Risco de não decidir:** identidade ambígua.
**Pergunta de decisão:** `codpes` é identidade única do operador?
**Opções:**
- A: unique + reconciliação — trade-off: migração.
- B: não usar `codpes` para operador — trade-off: outro identificador.
- C: manter — trade-off: risco.
**Recomendação:** A.
**Dependências:** ACH-021, ACH-022.
**Confiança:** alta.

### ACH-027 — Sem proteção do último admin/autodemissão

**Categoria:** risco.
**Severidade:** média.
**Regras/fluxos:** RN-030; F-05.
**Fato observado:** `UserPolicy` impede excluir a si mesmo, mas o `UserForm` permite editar as próprias roles; não há verificação de “último Admin”; `RolePolicy` só protege o papel `Admin`, não `ADM` (`RolePolicy.php:47-52`).
**Evidência:** policies, form.
**Interpretação:** é possível ficar sem administrador ou remover o papel de quem ainda precisa dele.
**Impacto operacional:** bloqueio do painel; recuperação via tinker.
**Risco de não decidir:** indisponibilidade administrativa.
**Pergunta de decisão:** qual é a regra de ciclo de vida do administrador?
**Opções:**
- A: sempre manter ao menos um Admin — trade-off: validação extra.
- B: permitir e recuperar via CLI — trade-off: operação manual.
- C: segundo aprovador — trade-off: mais processo.
**Recomendação:** A.
**Dependências:** ACH-001, ACH-028.
**Confiança:** alta.

### ACH-028 — Dois sistemas de autorização paralelos

**Categoria:** decisão não documentada.
**Severidade:** média.
**Regras/fluxos:** RN-030, RN-033; F-01, F-05.
**Fato observado:** Spatie `web` controla `/lancamento` e policies; o pacote Senha Única cria permissões `senhaunica` e gates `admin`/`user`; `/loginas` e `/senhaunica-users` são protegidos por esses gates (`UserController.php:15-35`); `SENHAUNICA_ADMINS` não está documentado no README.
**Evidência:** pacote, config, `HasSenhaunica`.
**Interpretação:** existe um super-admin de ambiente e uma assunção de identidade fora do modelo de papéis do CotaG.
**Impacto operacional:** acesso não previsto; ações não auditadas.
**Risco de não decidir:** backdoor administrativo e confusão de responsabilidade.
**Pergunta de decisão:** o CotaG deve manter os gates/permissões do pacote Senha Única e o `/loginas`?
**Opções:**
- A: desabilitar o que não for usado — trade-off: menos recursos.
- B: manter e documentar/auditar — trade-off: mais controle.
- C: unificar no Spatie — trade-off: migração.
**Recomendação:** A se não houver uso; B se a assunção de identidade for necessária.
**Dependências:** ACH-001, ACH-018.
**Confiança:** média/alta.

### ACH-029 — Login sempre redireciona para `/lancamento`, inclusive Admin/Filament

**Categoria:** contradição de fluxo.
**Severidade:** média.
**Regras/fluxos:** F-01, F-02, F-05.
**Fato observado:** o login local redireciona para `/lancamento` (`login.blade.php:25`); o `LoginResponse` do Filament também (`AppServiceProvider.php:76-87`); o `ADM` sem `operar-sistema` recebe 403.
**Evidência:** arquivos acima.
**Interpretação:** o destino não considera o papel.
**Impacto operacional:** Admin cai na tela do operador; usuário sem permissão cai em 403.
**Risco de não decidir:** experiência confusa; suporte.
**Pergunta de decisão:** qual é o destino pós-login por papel?
**Opções:**
- A: Admin → `/admin`; Operador → `/lancamento` — trade-off: lógica por papel.
- B: sempre `/lancamento` — trade-off: atual.
- C: sempre `/dashboard` com links — trade-off: mais um clique.
**Recomendação:** A.
**Dependências:** ACH-001, ACH-002.
**Confiança:** alta.

### ACH-030 — Documentação normativa contraditória

**Categoria:** contradição.
**Severidade:** baixa/média.
**Regras/fluxos:** todos.
**Fato observado:** README diz “já está em produção” (`README.md:25`); AGENTE diz “fase de desenvolvimento ativo” (`AGENTE.md:33`); docs/arquitetura diz “implementação final” e “fonte da verdade” (`docs/arquitetura/01:5-7`); termo de abertura é de starter kit e exclui negócio (`docs/termo_abertura_projeto.md:1,72-74`); CHANGELOG está em “Não lançado”; README é 1.0.0; AGENTE é 0.2.1.
**Evidência:** documentos.
**Interpretação:** não há documento normativo único.
**Impacto operacional:** cada equipe escolhe uma fonte; decisões ficam inconsistentes.
**Risco de não decidir:** retrabalho permanente.
**Pergunta de decisão:** qual documento é normativo para o CotaG?
**Opções:**
- A: novo documento de requisitos — trade-off: esforço inicial.
- B: README como norma — trade-off: precisa ser corrigido.
- C: AGENTE/docs como norma — trade-off: precisam ser corrigidos.
**Recomendação:** A, com versionamento e aprovação.
**Dependências:** todas as decisões.
**Confiança:** alta.

### ACH-031 — Código, tabelas e telas órfãs

**Categoria:** lacuna/documental.
**Severidade:** baixa.
**Regras/fluxos:** F-18.
**Fato observado:** `Mensagem` sem uso; `CreateExtrato`/`EditExtrato` sem rota; `ExtratoForm` vazio; `Pessoa::grupos()` referencia `Grupo` inexistente; `PessoaResource` citado no AGENTE não existe; notificações não usadas.
**Evidência:** arquivos.
**Interpretação:** resíduos de iterações e documentos desatualizados.
**Impacto operacional:** confusão de manutenção; falsas expectativas.
**Risco de não decidir:** esforço desperdiçado.
**Pergunta de decisão:** o que será removido e o que será implementado?
**Opções:**
- A: remover o que não tem fluxo — trade-off: perda de trabalho futuro.
- B: implementar o que foi prometido — trade-off: escopo.
- C: manter e documentar como não usado — trade-off: dívida.
**Recomendação:** A, com registro do que foi descartado.
**Dependências:** ACH-030.
**Confiança:** alta.

### ACH-032 — Dados pessoais de OAuth gravados por padrão sem retenção

**Categoria:** risco de ciclo de vida de dados.
**Severidade:** baixa/média.
**Regras/fluxos:** F-01.
**Fato observado:** o pacote grava `storage/app/private/debug/oauth/{codpes}.json` quando `senhaunica.debug` é verdadeiro (`vendor/uspdev/senhaunica-socialite/src/Server.php:61-62`); o default é `true` (`vendor/uspdev/senhaunica-socialite/config/config.php:69`); `.env.example` não define `SENHAUNICA_DEBUG`; há um arquivo local com nome, e-mails, telefone e vínculos.
**Evidência:** pacote, config, arquivo local.
**Interpretação:** dados pessoais são retidos sem política, fora do banco e sem auditoria.
**Impacto operacional:** exposição/descarte irregular; o arquivo não é gerenciado pela aplicação.
**Risco de não decidir:** retenção indefinida de PII.
**Pergunta de decisão:** o debug OAuth deve existir em produção? Por quanto tempo?
**Opções:**
- A: desligar em produção — trade-off: menos diagnóstico.
- B: manter com retenção curta — trade-off: processo de limpeza.
- C: manter indefinidamente — trade-off: risco.
**Recomendação:** A em produção; B se o suporte precisar.
**Dependências:** nenhuma.
**Confiança:** alta.

---

## 9. Contradições documentais

| # | Documento afirma | Comportamento observado / evidência | Impacto | Achado |
|---|---|---|---|---|
| 1 | README: “O sistema já está em produção” (`README.md:25`) | AGENTE: “fase de desenvolvimento ativo” (`AGENTE.md:33`); CHANGELOG em “Não lançado”; docs/arquitetura: “implementação final” (`docs/arquitetura/01:5`) | Não se sabe o que está congelado nem o que pode mudar | ACH-030 |
| 2 | README/AGENTE/docs: papéis `ADM`/`OPR` (`README.md:42`; `AGENTE.md:230-231`; `docs/arquitetura/01:33`) | Seeders criam `Admin`/`Operador` (`RoleSeeder.php:34-35`); policies exigem `Admin`/`Operador` | Administrador do README é negado; operador não entra no painel | ACH-001 |
| 3 | README: atribuir `ADM` (`README.md:190-196`) | Policy só aceita `Admin`; painel aceita `ADM|Admin` (`AdminPanelProvider.php:124`) | Seguir o README produz admin sem acesso aos recursos | ACH-001 |
| 4 | Docs/analise/04: operador gerencia cotas e vê extrato (`docs/analise/04:31-35`) | Painel exige `ADM|Admin`; operador recebe 403 (`AdminPanelProvider.php:124`); menu mostra link (`navigation.blade.php:80-82`) | Operador não consegue operar como documentado | ACH-002 |
| 5 | Docs/analise/04: Admin “desativa contas” (`docs/analise/04:45`) | Não há campo de ativo/inativo em `users` nem em `pessoas` | Não há desativação, só exclusão destrutiva | ACH-020 |
| 6 | Docs/analise/03: especial tem “prioridade absoluta” e garante “maior benefício” (`docs/analise/03:11`) | Serviço soma múltiplas especiais (`CotaService.php:96-109`); especial menor que regular reduz a cota | O “maior benefício” é falso em casos comuns | ACH-006 |
| 7 | AGENTE/docs: histórico preservado (`AGENTE.md:132`) | FKs em cascata apagam lançamentos ao excluir pessoa/usuário | Histórico pode ser destruído | ACH-003, ACH-004 |
| 8 | README: “Logs detalhados de todas as operações críticas” (`README.md:41`) | Login, vínculo e atribuição de papel não são auditados; cascatas não geram log | A trilha não cobre operações críticas de identidade/permissão | ACH-018 |
| 9 | Docs/arquitetura/02: extrato com “exportação” (`docs/arquitetura/02:27`) | Não há ação de exportação em `ExtratosTable` | Relatórios precisam ser feitos à mão | ACH-024 |
| 10 | Docs/analise/01: login local por NUSP + senha (`docs/analise/01:19-24`) | Login local por e-mail (`LoginForm.php:15-19`) | Identidade de login mudou sem decisão registrada | ACH-021 |
| 11 | Docs/analise/01: Admin gera senha aleatória e exibe (`docs/analise/01:112-115`) | Admin digita a senha no formulário (`UserForm.php:32-37`) | Provisionamento diferente do legado | ACH-021 |
| 12 | Docs/analise/01: extrato com botões “mês atual” e “todos” (`docs/analise/01:131-134`) | Uma listagem com filtros de data (`ExtratosTable.php:19-99`) | Funcionalidade de consulta rápida não existe | ACH-024 |
| 13 | Docs/analise/02 e AGENTE: cota especial uma-para-um (`docs/analise/02`; `Pessoa.php:84-92`) | Sem unique; serviço soma múltiplas (`CotaService.php:96-109`) | O modelo documentado não é o modelo real | ACH-006 |
| 14 | AGENTE: “Local-First” importa/atualiza vínculos (`AGENTE.md:146`) | Pessoa local por `codpes` não é atualizada; merge ignora Replicado | Dados obsoletos permanentes | ACH-010 |
| 15 | Termo de abertura: “Starter Kit”, exclui negócio (`docs/termo_abertura_projeto.md:1,72-74`) | README descreve CotaG em produção | Não há escopo normativo | ACH-030 |
| 16 | README: docentes, funcionários e alunos têm cota (`README.md:15,28`) | `SERVIDOR` não tem cota no seeder; vínculos não listados ficam com 0 | Funcionários e outros grupos podem ter cota zero | ACH-019 |
| 17 | Docs/analise/04: Admin é o único que vê logs (`docs/analise/04:51`) | `AuditResource` é Admin-only, mas `/senhaunica-users` tem outro canal | Há um canal de administração não documentado | ACH-028 |
| 18 | Docs/analise/01: legado grava login em `LOG` | Novo sistema não grava login; `audits` só de modelo | Perda de trilha de autenticação | ACH-018 |
| 19 | Docs/analise/04: beneficiário não interage (`docs/analise/04:9-11`) | `/register` público e login local | Fluxo de registro órfão | ACH-022 |
| 20 | Docs/arquitetura/01: `audits` substitui `LOG` (`docs/arquitetura/01:19`) | `audits` não cobre login; `LOG` não existe | A substituição é incompleta | ACH-018 |
| 21 | Docs/analise/02: `MENSAGEM`, `OU`, `GRUPO` do legado | `mensagens` existe sem uso; `User::grupos()` referencia `Grupo` inexistente | Resíduos e falsas expectativas | ACH-031 |
| 22 | AGENTE: `PessoaResource` em `app/Filament/Resources` (`AGENTE.md:98`) | Não existe `PessoaResource` | Documentação desatualizada | ACH-031 |

---

## 10. Matriz de rastreabilidade

| Fluxo | Regras | Evidência principal | Achados |
|---|---|---|---|
| F-01 Senha Única | RN-033, RN-048 | `vendor/uspdev/senhaunica-socialite/src/Http/Controllers/SenhaunicaController.php:50-62`; `HasSenhaunica.php:160-174` | ACH-001, ACH-018, ACH-026, ACH-028, ACH-032 |
| F-02 Login local | RN-048 | `LoginForm.php:29-45`; `login.blade.php:25` | ACH-021, ACH-029 |
| F-03 Registro público | RN-034 | `routes/auth.php:7-9`; `register.blade.php:108-131` | ACH-022, ACH-026 |
| F-04 Recuperação/verificação | — | `routes/auth.php:14-30`; `AppServiceProvider.php:37-44` | ACH-021, ACH-022 |
| F-05 Usuários/papéis | RN-030, RN-036, RN-046, RN-047 | `UserForm.php:17-43`; `RoleResource.php`; `UserPolicy.php` | ACH-001, ACH-003, ACH-018, ACH-027 |
| F-06 Busca de pessoa | RN-018–RN-023 | `ManageLancamentos.php:153-224`; `ReplicadoService.php:75-198` | ACH-010, ACH-011, ACH-012, ACH-025 |
| F-07 Importação | RN-018–RN-023, RN-049 | `ManageLancamentos.php:105-133`; `CotaEspecialForm.php:23-59` | ACH-010, ACH-011, ACH-025 |
| F-08 Seleção/exibição | RN-001–RN-012 | `ManageLancamentos.php:77-100`; `manage-lancamentos.blade.php:92-108` | ACH-006, ACH-023 |
| F-09 Débito | RN-009–RN-017 | `ManageLancamentos.php:238-293`; `CotaService.php:25-38` | ACH-008, ACH-015, ACH-016, ACH-017 |
| F-10 Crédito | RN-010, RN-011, RN-015 | `ManageLancamentos.php:238-293`; `CotaService.php:133-142` | ACH-007, ACH-015, ACH-016 |
| F-11 Cota/saldo | RN-001–RN-012 | `CotaService.php:51-142` | ACH-005, ACH-006, ACH-013, ACH-014 |
| F-12 Virada de mês | RN-012–RN-014 | `Lancamento.php:73-77`; `CotaService.php:63-69` | ACH-005, ACH-007, ACH-008, ACH-009 |
| F-13 Cotas regulares | RN-005–RN-008, RN-026, RN-028, RN-029 | `CotaForm.php:15-24`; `CotasTable.php`; migration `cotas` | ACH-013, ACH-014, ACH-019 |
| F-14 Cotas especiais | RN-002–RN-004, RN-025, RN-027 | `CotaEspecialForm.php`; `CotaService.php:96-109` | ACH-005, ACH-006, ACH-025 |
| F-15 Extrato | RN-038 | `ExtratosTable.php:19-108` | ACH-002, ACH-024 |
| F-16 Auditoria | RN-036, RN-037 | `AuditResource.php`; `RoleObserver.php`; `PermissionObserver.php` | ACH-018 |
| F-17 Exclusões | RN-016, RN-017, RN-035 | migrations; `profile/delete-user-form.blade.php` | ACH-003, ACH-004 |
| F-18 Órfãos | — | `Mensagem.php`; `CreateExtrato.php`; `EditExtrato.php`; `Pessoa.php:27-30` | ACH-031 |

---

## 11. Perguntas candidatas para a lapidação

> Não são perguntas desta sessão. São candidatas para as rodadas posteriores, agrupadas por tema, prioridade e dependência. A prioridade P0 destrava o maior número de outras decisões.

### Rodada 1 — Fundamentos (P0)

| ID | Pergunta | Destrava | Depende de |
|---|---|---|---|
| Q-01 | O que é uma cota: direito mensal não acumulável, orçamento, limite de consumo ou saldo? | RN-001–RN-012, RN-028 | — |
| Q-02 | O que é um crédito: compra, bonificação, ajuste ou transferência? Expira? | RN-007, RN-011, RN-044 | Q-01 |
| Q-03 | O que é um débito: consumo, perda, ajuste ou estorno? Pode ser negativo? | RN-009, RN-010, RN-015 | Q-01 |
| Q-04 | O que é saldo negativo: dívida, tolerância, erro ou estado proibido? | RN-010, RN-042 | Q-01, Q-02 |
| Q-05 | Qual é a precedência entre cota especial, cota regular e créditos? | RN-001–RN-004, RN-013 | Q-01 |
| Q-06 | O que é o “mês”: calendário civil, ciclo de fechamento ou período letivo? Qual o instante de corte? | RN-013, RN-014 | Q-01 |
| Q-07 | A cota especial é única, piso, teto ou adicional? Tem vigência, motivo e autor? | RN-002–RN-004, RN-025, RN-027 | Q-05 |
| Q-08 | Quando a pessoa tem vários vínculos, qual vínculo define a cota: maior, soma, principal ou último? | RN-005, RN-006 | Q-01 |
| Q-09 | Qual é o valor oficial de cota por vínculo e qual é a origem desse valor? | RN-039, RN-040 | Q-01, Q-08 |
| Q-10 | A elegibilidade é restrita ao IME (codundclg 45) ou a toda a USP? | RN-021, RN-049 | Q-08 |

### Rodada 2 — Ciclo de vida e temporalidade (P1)

| ID | Pergunta | Destrava | Depende de |
|---|---|---|---|
| Q-11 | O saldo de um mês fechado é imutável? Como se prova o direito de uma pessoa em uma data? | RN-028, RN-029 | Q-06 |
| Q-12 | Lançamento retroativo é permitido? Quem pode, com qual justificativa e como afeta o mês fechado? | RN-014 | Q-06, Q-11 |
| Q-13 | Como se corrige um lançamento errado: estorno, substituição, exclusão? Quem aprova? | RN-015–RN-017 | Q-11, Q-12 |
| Q-14 | O que acontece se a pessoa perder o vínculo, for desligada, falecer ou mudar de vínculo no meio do mês? | RN-020, RN-019 | Q-10 |
| Q-15 | O que acontece com créditos não usados na virada? | RN-011 | Q-02, Q-06 |
| Q-16 | Pessoa sem vínculo pode consumir? Com qual cota e por qual processo? | RN-008, RN-010 | Q-10, Q-14 |
| Q-17 | Pessoa e usuário podem ser excluídos? O que acontece com o histórico? | RN-016, RN-017, RN-035 | Q-11 |

### Rodada 3 — Autorização, segregação e auditoria (P1)

| ID | Pergunta | Destrava | Depende de |
|---|---|---|---|
| Q-18 | Qual é o conjunto canônico de papéis e a matriz de acesso? | RN-030, RN-031, RN-046, RN-047 | — |
| Q-19 | Operador pode gerenciar cotas e ver extrato global? | RN-030, RN-031 | Q-18 |
| Q-20 | Quem pode conceder cota especial e crédito? Com qual limite e aprovação? | RN-044 | Q-18, Q-07 |
| Q-21 | Operador pode lançar para si mesmo ou para pessoa vinculada a ele? | RN-041, RN-044 | Q-20 |
| Q-22 | O que precisa ser auditado: login, vínculo, papel, cota, lançamento, exclusão? Por quanto tempo? | RN-036, RN-037 | Q-18 |
| Q-23 | O extrato é global, por unidade, por operador ou por pessoa? Quem pode ver? | RN-038 | Q-10, Q-18 |
| Q-24 | O `/loginas` e o gerenciamento de permissões do pacote Senha Única devem existir? | ACH-028 | Q-18 |
| Q-25 | Qual é a regra do último administrador e da autodemissão? | RN-030 | Q-18 |

### Rodada 4 — Integrações, exceções e identidade (P2)

| ID | Pergunta | Destrava | Depende de |
|---|---|---|---|
| Q-26 | Quem é a autoridade sobre o vínculo: Replicado, sistema ou ambos? Com qual frequência atualizar? | RN-018, RN-019 | Q-10, Q-14 |
| Q-27 | O que fazer quando o Replicado está indisponível ou falha parcialmente? | RN-018, RN-019 | Q-26 |
| Q-28 | Qual campo de nome é oficial para busca e exibição? Nome social conta? | RN-022 | Q-26 |
| Q-29 | O login local deve ser por NUSP ou e-mail? Quem provisiona a senha? | RN-048 | Q-18 |
| Q-30 | O registro público deve existir? Quem aprova um operador? | RN-034 | Q-18, Q-29 |
| Q-31 | `codpes` é identidade única do operador? Como reconciliar duplicatas? | RN-033 | Q-29, Q-30 |

### Rodada 5 — Operação, relatórios e retenção (P3)

| ID | Pergunta | Destrava | Depende de |
|---|---|---|---|
| Q-32 | Quais relatórios e exportações são obrigatórios e para quem? | RN-038 | Q-23 |
| Q-33 | Quais notificações existem (saldo negativo, crédito, fechamento) e com qual gatilho? | RN-042 | Q-04, Q-11 |
| Q-34 | Qual é o processo de cobrança/regularização do saldo negativo? | RN-042 | Q-04, Q-33 |
| Q-35 | Quais campos são obrigatórios em cada lançamento (motivo, recibo, referência de pagamento)? | RN-045 | Q-02, Q-03 |
| Q-36 | Como evitar lançamento duplicado? | RN-043 | Q-35 |
| Q-37 | Qual é a retenção de dados pessoais e de auditoria? O debug OAuth fica ligado? | ACH-032 | Q-22 |
| Q-38 | O que fazer com os dados do legado (lançamentos, saldos, usuários)? | RN-048 | Q-11, Q-29 |
| Q-39 | Quais são os critérios de aceite para considerar a operação “correta”? | todos | todas |

---

## 12. Recomendações de decisão

Estas são decisões de negócio, não de implementação. Cada pacote destrava um conjunto de regras.

| ID | Decisão | Perguntas | Destrava | Depende de |
|---|---|---|---|---|
| D-01 | Definir o vocabulário normativo: cota, crédito, débito, saldo, mês, especial. | Q-01–Q-06 | RN-001–RN-014, RN-028 | — |
| D-02 | Definir a política de saldo negativo e de crédito. | Q-02, Q-04, Q-15, Q-34 | RN-010, RN-011, RN-042 | D-01 |
| D-03 | Definir a composição e a vigência da cota regular e da especial. | Q-05, Q-07, Q-08, Q-09 | RN-001–RN-008, RN-027, RN-028 | D-01 |
| D-04 | Definir fechamento, retroatividade e correção. | Q-11–Q-13 | RN-014–RN-017, RN-029 | D-01, D-03 |
| D-05 | Definir autoridade e atualização de vínculo. | Q-26–Q-28 | RN-018–RN-023 | D-01 |
| D-06 | Definir elegibilidade e escopo de unidade. | Q-10, Q-14, Q-16 | RN-021, RN-049 | D-05 |
| D-07 | Definir papéis, segregação e matriz de acesso. | Q-18–Q-21, Q-25 | RN-030, RN-031, RN-041, RN-044, RN-046, RN-047 | — |
| D-08 | Definir o que é auditável e por quanto tempo. | Q-22, Q-37 | RN-036, RN-037 | D-04, D-07 |
| D-09 | Definir relatórios, notificações e cobrança. | Q-32–Q-34 | RN-038, RN-042 | D-02, D-08 |
| D-10 | Definir a origem dos valores oficiais de cota e o tratamento do legado. | Q-09, Q-38 | RN-039, RN-040, RN-048 | D-03, D-06 |

**Sequência mínima recomendada:** D-01 → D-03 → D-04 → D-02 → D-05/D-06 → D-07 → D-08 → D-09/D-10. D-01 e D-07 podem correr em paralelo; D-04 depende de D-01/D-03; D-08 depende de D-04.

**O que não decidir agora custa:** sem D-01, qualquer correção de cálculo é arbitrária; sem D-04, a auditoria não prova nada; sem D-05/D-06, a cota pode ser de outra unidade ou de um vínculo que não existe mais; sem D-07, o operador continua podendo conceder crédito a si; sem D-08, a exclusão continua apagando o histórico.

---

## 13. Anexos

### 13.1 Glossário operacional

| Termo | Definição usada neste relatório |
|---|---|
| **Beneficiário** | Pessoa que consome a cota; registro `Pessoa`, sem login. |
| **Operador** | Usuário de balcão; papel `Operador`, acesso a `/lancamento`. |
| **Admin** | Usuário de gestão; papel `Admin`, acesso a `/admin`. |
| **Cota regular** | Regra por `tipo_vinculo` na tabela `cotas`. |
| **Cota especial** | Exceção por pessoa na tabela `cota_especials`. |
| **Crédito** | Lançamento tipo `0`, somado ao saldo do mês. |
| **Débito** | Lançamento tipo `1`, subtraído do saldo do mês. |
| **Saldo** | `cota + créditos do mês − débitos do mês`. |
| **Virada** | Mudança de mês civil; não há evento, só consulta. |
| **Fechamento** | Processo (inexistente) de congelar um período. |
| **Estorno** | Correção por lançamento contrário (inexistente como fluxo). |
| **Vínculo** | Tipo de relação da pessoa com a USP, vindo do Replicado e congelado localmente. |
| **Replicado** | Base corporativa da USP consultada por SQL. |
| **Senha Única** | OAuth1 da USP; autentica e aplica permissões do guard `senhaunica`. |
| **Auditoria** | Tabela `audits` do `owen-it/laravel-auditing`. |
| **Policy** | Regra de autorização Laravel/Filament. |

### 13.2 Arquivos analisados

**Documentação:** `README.md`, `AGENTE.md`, `CHANGELOG.md`, `docs/analise/01-mapeamento-funcionalidades.md`, `docs/analise/02-modelo-de-dados.md`, `docs/analise/03-regras-de-negocio.md`, `docs/analise/04-perfis-e-permissoes.md`, `docs/arquitetura/01-mapeamento-entidade-model.md`, `docs/arquitetura/02-mapeamento-logica-arquitetura.md`, `docs/termo_abertura_projeto.md`, `docs/plano_acao_alto_nivel.md`, `docs/DEPLOY_PORTAINER.md`.

**Rotas e bootstrap:** `routes/web.php`, `routes/auth.php`, `routes/console.php`, `bootstrap/app.php`.

**Models:** `Pessoa`, `Vinculo`, `Cota`, `CotaEspecial`, `Lancamento`, `User`, `Role`, `Permission`, `Mensagem`.

**Services:** `CotaService`, `ReplicadoService`.

**Livewire/UI:** `ManageLancamentos`, `LoginForm`, `login.blade.php`, `register.blade.php`, `manage-lancamentos.blade.php`, `navigation.blade.php`, `profile/delete-user-form.blade.php`, `dashboard.blade.php`.

**Filament:** `UserResource`, `RoleResource`, `PermissionResource`, `CotaResource`, `CotaEspecialResource`, `ExtratoResource`, `AuditResource`, `Dashboard`, `NavigationCardsWidget`, `AdminPanelProvider`, formulários e tabelas relacionados.

**Policies/Providers:** `UserPolicy`, `RolePolicy`, `PermissionPolicy`, `AuditPolicy`, `CotaPolicy`, `CotaEspecialPolicy`, `LancamentoPolicy`, `AppServiceProvider`, `VoltServiceProvider`.

**Migrations:** todas as de `database/migrations/`, com ênfase em `users`, `pessoas`, `vinculos`, `cotas`, `cota_especials`, `lancamentos`, `audits`, `mensagens`.

**Seeders/factories:** `DatabaseSeeder`, `RoleSeeder`, `FilamentAdminSeeder`, `CotaSeeder`, factories de `Pessoa`, `Vinculo`, `Cota`, `CotaEspecial`, `Lancamento`, `User`.

**Testes:** todos os arquivos de `tests/Feature`, `tests/Unit`, `tests/Browser`, `tests/Fakes`.

**Configuração:** `config/app.php`, `config/auth.php`, `config/audit.php`, `config/permission.php`, `config/services.php`, `.env.example`, `phpunit.xml`, `docker-compose.prod.yml`, `docker/production/entrypoint.sh`.

**Pacote externo:** `vendor/uspdev/senhaunica-socialite` (rotas, controller, trait, providers, config).

**Artefato local:** `storage/app/private/debug/oauth/7762448.json`.

### 13.3 Índice de achados por severidade

| Severidade | IDs |
|---|---|
| Bloqueante | ACH-001, ACH-002, ACH-003, ACH-004, ACH-005, ACH-006, ACH-007, ACH-008, ACH-009 |
| Alta | ACH-010, ACH-011, ACH-012, ACH-013, ACH-014, ACH-015, ACH-016, ACH-017, ACH-018, ACH-019, ACH-020 |
| Média | ACH-021, ACH-022, ACH-023, ACH-024, ACH-025, ACH-026, ACH-027, ACH-028, ACH-029 |
| Baixa/Média | ACH-030, ACH-032 |
| Baixa | ACH-031 |

### 13.4 Fora de escopo, registrado para não virar “achado”

- **Runtime:** ausência de PHP no ambiente; não foi possível executar a aplicação.
- **Qualidade de código:** `User::grupos()` sem importação de `BelongsToMany`/`Grupo`; `CreateExtrato`/`EditExtrato` sem rota; `ExtratoForm` vazio; `Mensagem` vazio.
- **Fortify:** `AppServiceProvider` referencia `Laravel\Fortify\Contracts\LoginResponse`, mas o pacote não está instalado; o binding é código morto, não um fluxo ativo.
- **Performance:** `buscarPessoasPorNome` faz uma consulta de vínculos por pessoa (N+1) e pode importar muitos resultados; fora do escopo, mas afeta a operação em buscas amplas.
- **Segurança de implementação:** hashing, CSRF, OAuth, headers, rate limit; não avaliados como regra de negócio.
- **Testes:** a suíte não foi executada; há testes com API inexistente e papéis não semeados. Eles não foram usados como prova.
- **UI/estilo:** mensagens, ícones, layout, dark mode.

### 13.5 Limitações do relatório

- A análise é estática e baseada no estado atual do repositório.
- Não há código legado para confrontar as regras declaradas em `docs/analise`.
- Não há dados de produção para medir impacto real (quantas pessoas, quantos lançamentos, qual percentual de saldo negativo).
- Não há acesso ao Replicado para validar a semântica de vínculo, nome e unidade.
- As conclusões sobre autorização assumem o comportamento padrão do Laravel/Filament/Spatie descrito no código; não foram observadas em execução.

---

Análise concluída. Aguardando o comando para iniciar a rodada de perguntas.

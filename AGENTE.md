# AGENTE.md - Instruções para Agente de IA

**Versão:** 0.2.1
**Data:** 2025-11-19

## Sobre Este Arquivo

Este arquivo fornece instruções e contexto específicos para o **Agente de IA** ao trabalhar neste projeto. Ele complementa a documentação geral do projeto e ajuda o assistente a entender as particularidades, padrões e requisitos deste sistema.

---

## 1. Contexto do Projeto

### 1.1. Identificação

- **Nome:** CotaG (Sistema de Gestão de Cotas de Gráfica)
- **Descrição:** Sistema de gestão de cotas de impressão/cópias para a comunidade acadêmica do IME-USP.
- **Framework:** Laravel 12 com TALL Stack (Tailwind, Alpine.js, Livewire) e FilamentPHP.
- **Propósito:** Migração de sistema legado Java/JSF para Laravel moderno.

### 1.2. Natureza do Sistema

O **CotaG** gerencia o uso de serviços de gráfica (impressões/cópias) por docentes, funcionários e alunos. O sistema controla:

- **Cotas Mensais:** Definidas por vínculo USP (ex: Docentes têm X cópias, Alunos têm Y).
- **Cotas Especiais:** Exceções para indivíduos específicos que sobrepõem a cota regular.
- **Lançamentos:** Débitos (consumo de cópias) e Créditos (adição de saldo extra).
- **Saldo:** Calculado dinamicamente (Cota + Créditos - Débitos).
- **Integração:** Busca de dados de pessoas e vínculos via Replicado USP.

### 1.3. Fase Atual do Projeto

O projeto está em **fase de desenvolvimento ativo**, com:
- Estrutura base do Laravel 12 instalada.
- Integração com Replicado e Senha Única configurada.
- Funcionalidades de Lançamento (Débito/Crédito) implementadas.
- Painel Administrativo (Filament) em construção.
- Migração das regras de negócio do sistema legado concluída.

---

## 2. Arquitetura e Padrões Obrigatórios

### 2.1. Estrutura de Camadas

**SEMPRE** siga esta separação de responsabilidades:

```
┌─────────────────────────┐
│   Filament Resources    │ ← Admin UI & CRUDs
├─────────────────────────┤
│   Livewire Components   │ ← Frontend UI (Operador)
├─────────────────────────┤
│   Services              │ ← Lógica de negócio complexa
├─────────────────────────┤
│   Models (Eloquent)     │ ← Acesso a dados e Scopes
└─────────────────────────┘
```

### 2.2. Services (Lógica de Negócio)

**Localização:** `app/Services/`

**Services Principais:**

| Service | Responsabilidade |
|---------|------------------|
| `CotaService` | Cálculo de cota, saldo e processamento de lançamentos. |
| `ReplicadoService` | Integração com `uspdev/replicado` (busca de pessoas e vínculos). |

**Padrão de Implementação:**
```php
namespace App\Services;

class CotaService
{
    public function __construct(
        private ReplicadoService $replicadoService
    ) {}

    /**
     * Calcula o saldo atual de uma pessoa.
     *
     * @param \App\Models\Pessoa $pessoa
     * @return int
     */
    public function calcularSaldo(Pessoa $pessoa): int
    {
        // Implementação...
    }
}
```

### 2.3. Filament (Painel Administrativo)

O sistema utiliza **Filament v4** para a área administrativa.

- **Resources:** `app/Filament/Resources/` (ex: `PessoaResource`, `CotaResource`).
- **Pages:** `app/Filament/Pages/`.
- **Widgets:** `app/Filament/Widgets/`.

**Regra:** Use Filament para todas as interfaces de gerenciamento (CRUDs de Cotas, Usuários, Logs). Use Livewire "puro" apenas para interfaces específicas do Operador (ex: Tela de Lançamento rápida).

---

## 3. Regras de Negócio Críticas

### 3.1. Cálculo de Cota Mensal

**Ordem de Prioridade:**
1. **Cota Especial** (se existir registro em `cota_especiais` para o codpes) → Valor definido.
2. **Cota Regular** (se não houver especial):
   - Busca todos os vínculos ativos da pessoa.
   - Consulta a tabela `cotas` para cada vínculo.
   - **Resultado:** Maior valor encontrado entre os vínculos.
3. **Sem cota** → Valor = 0.

### 3.2. Cálculo de Saldo

```
Saldo = Cota Mensal + Soma(Créditos do Mês) - Soma(Débitos do Mês)
```

**Regras:**
- **Dinâmico:** O saldo é calculado em tempo real, não armazenado.
- **Mensal:** Considera apenas lançamentos (débitos/créditos) do **mês e ano atuais**.
- **Renovação Implícita:** Ao virar o mês, os lançamentos do mês anterior deixam de contar, "renovando" a cota automaticamente.

### 3.3. Lançamentos (Débito e Crédito)

- **Saldo Negativo:** O sistema **PERMITE** que o saldo fique negativo. Não há bloqueio de operação por falta de saldo. O controle é administrativo.
- **Histórico:** Todos os lançamentos devem ser preservados para auditoria.
- **Tipos:**
  - `Débito`: Consumo de cópias (subtrai do saldo).
  - `Crédito`: Adição de cópias (soma ao saldo).

---

## 4. Integrações USP

### 4.1. Replicado (uspdev/replicado)

**Service:** `ReplicadoService`
**Propósito:** Buscar dados de pessoas e vínculos.

**Estratégia "Local-First":**
1. Ao buscar uma pessoa (por NUSP, nome, etc.), primeiro tenta encontrar na tabela local `pessoas`.
2. Se não encontrar, busca no Replicado.
3. Se encontrar no Replicado, **importa/atualiza** os dados na tabela local `pessoas` (incluindo vínculos).

### 4.2. Senha Única USP (uspdev/senhaunica-socialite)

**Uso:** Autenticação de usuários (Admin e Operadores).
**Guards:** `web` (padrão) e `senhaunica`.

---

## 5. Padrões de Código

### 5.1. Nomenclatura

| Elemento | Convenção | Exemplo |
|----------|-----------|---------|
| Model | Singular | `Pessoa`, `Lancamento`, `Cota` |
| Controller | Singular + `Controller` | `LancamentoController` |
| Service | Substantivo + `Service` | `CotaService` |
| Filament Resource | Singular + `Resource` | `PessoaResource` |
| Tabela BD | Plural (`snake_case`) | `pessoas`, `lancamentos`, `cotas` |

### 5.2. Formatação e Qualidade

- **Pint:** Execute `./vendor/bin/pint` antes de commits.
- **Larastan:** Execute `./vendor/bin/phpstan analyse`.
- **Testes:** Mantenha cobertura de testes para regras de negócio (`CotaService`).

### 5.3. Localização

- Use `__()` para todos os textos visíveis.
- Arquivos de tradução em `lang/pt_BR.json`.

---

## 6. Banco de Dados

### 6.1. Principais Tabelas

```mermaid
erDiagram
    pessoas ||--o{ lancamentos : "possui"
    pessoas ||--o| cota_especiais : "pode ter"
    pessoas {
        bigint codpes PK
        string nome
        json vinculos
    }
    lancamentos {
        id pk
        bigint pessoa_codpes FK
        int valor
        string tipo "D=Débito, C=Crédito"
        date data
        bigint usuario_id FK
    }
    cotas {
        id pk
        string vinculo "DOCENTE, ALUNO, etc"
        int quantidade
    }
    cota_especiais {
        id pk
        bigint codpes FK
        int quantidade
    }
```

### 6.2. Migrations

- Use `constrained()` para chaves estrangeiras.
- Nomes de tabelas no plural.
- Timestamps em todas as tabelas.

---

## 7. Autenticação e Autorização

### 7.1. Perfis (Roles)

| Role | Descrição | Acesso |
|------|-----------|--------|
| `ADM` | Administrador | Acesso total ao Filament e configurações. |
| `OPR` | Operador | Acesso à tela de Lançamentos e consulta de pessoas. |

**Pacote:** `spatie/laravel-permission`.

---

## 8. Workflow de Desenvolvimento

### 8.1. Commits e PRs

- **Conventional Commits:** `feat:`, `fix:`, `docs:`, `chore:`, `refactor:`.
- **Escopo Opcional:** `feat(lancamento):`, `fix(replicado):`.
- **Descrição:** Use listas (`-`) para detalhar mudanças.
- **Referência:** Sempre inclua `Ref: #<ID>` ou `Closes #<ID>`.

**Exemplo:**
```text
feat(lancamento): permite saldo negativo

- Remove validação que impedia débito sem saldo
- Adiciona alerta visual quando saldo < 0
- Atualiza testes do CotaService

Ref: #42
```

### 8.2. Testes

- **Unit:** Teste regras de cálculo de cota e saldo em `CotaServiceTest`.
- **Feature:** Teste fluxos de lançamento e integração com Filament.
- **Fakes:** Use `FakeReplicadoService` para simular respostas da USP.

---

## 9. Comandos Úteis

```bash
# Iniciar ambiente (Sail)
./vendor/bin/sail up -d

# Formatar código
./vendor/bin/sail bin pint

# Rodar testes
./vendor/bin/sail artisan test

# Criar usuário Filament
./vendor/bin/sail artisan make:filament-user
```

---

## 10. Documentos de Referência

### 10.1. Documentação do Projeto

| Documento | Propósito |
|-----------|-----------|
| `README.md` | Visão geral, instalação, uso básico |
| `docs/termo_abertura_projeto.md` | Escopo, objetivos, stakeholders |
| `docs/guia_de_desenvolvimento.md` | Metodologia, workflow, ferramentas |
| `docs/padroes_codigo_boas_praticas.md` | Padrões obrigatórios |
| `docs/analise/01-mapeamento-funcionalidades.md` | Funcionalidades do sistema |
| `docs/analise/03-regras-de-negocio.md` | Regras de negócio detalhadas |
| `docs/arquitetura/01-mapeamento-entidade-model.md` | Estrutura do banco de dados |
| `docs/arquitetura/02-mapeamento-logica-arquitetura.md` | Arquitetura de software |
| `CHANGELOG.md` | Histórico de mudanças |

---

## 11. Princípios Gerais

### 11.1. SOLID

- **Single Responsibility:** Uma classe, uma responsabilidade
- **Open/Closed:** Aberto para extensão, fechado para modificação
- **Liskov Substitution:** Subclasses substituíveis por suas bases
- **Interface Segregation:** Interfaces específicas, não genéricas
- **Dependency Inversion:** Depender de abstrações, não concretudes

### 11.2. DRY (Don't Repeat Yourself)

Evite duplicação usando:
- Métodos privados/protegidos
- Traits
- Services/Actions
- Query Scopes
- Componentes Blade/Livewire
- Chaves de tradução

### 11.3. KISS (Keep It Simple, Stupid)

- Prefira simplicidade sobre complexidade
- Código legível > Código "inteligente"
- Não implemente funcionalidades não solicitadas (YAGNI)

---

## 12. Checklist de Qualidade

### 12.1. Antes de Criar PR

- [ ] Código formatado com Pint
- [ ] Análise estática (Larastan) sem erros
- [ ] Todos os testes passando
- [ ] DocBlocks em métodos públicos
- [ ] Textos usando `__()`
- [ ] Form Requests para validação
- [ ] Controllers enxutos
- [ ] Lógica de negócio em Services
- [ ] Commits vinculados à Issue
- [ ] Descrição do PR com `Closes #<ID>`

---

## 13. Glossário

| Termo | Significado |
|-------|-------------|
| **codpes** | Número USP (identificador único). |
| **Lançamento** | Registro de uma operação de débito ou crédito de cópias. |
| **Cota Regular** | Quantidade mensal de cópias definida por vínculo (ex: Docente = 300). |
| **Cota Especial** | Quantidade mensal definida individualmente (exceção). |
| **Vínculo** | Relação da pessoa com a USP (DOCENTE, SERVIDOR, ALUNOGR, etc.). |
| **Replicado** | Base de dados corporativa da USP. |
| **Filament** | Framework administrativo utilizado no projeto. |

# Arquitetura de Software e Mapeamento de Lógica

## 1. Objetivo

Este documento descreve a arquitetura final do sistema CotaG, construída sobre o **Laravel 12** e **FilamentPHP v4**, utilizando o padrão **TALL Stack** (Tailwind, Alpine, Laravel, Livewire). Ele detalha como as regras de negócio são implementadas tecnicamente, servindo como guia para manutenção e evolução do sistema.

## 2. Princípios Arquiteturais e Tecnologias

A arquitetura segue os princípios modernos do ecossistema Laravel:

1.  **FilamentPHP v4 como Core Administrativo**: Toda a interface de gerenciamento (CRUDs) e painéis administrativos é construída utilizando Resources do Filament. Isso garante padronização, segurança e rapidez no desenvolvimento.
2.  **Lógica de Negócio em Services**: Regras complexas (cálculo de saldo, integração Replicado) são isoladas em classes de Serviço (`app/Services`), desacoplando a regra do framework.
3.  **Livewire para Interatividade**: Interfaces complexas de operação (como a tela de Lançamentos) utilizam componentes Livewire dedicados, priorizando validação inline e feedback em tempo real sem excesso de JavaScript.
4.  **Auditoria Automática**: O pacote `owen-it/laravel-auditing` é utilizado para rastrear mudanças críticas nos dados, garantindo rastreabilidade sem poluir o código de negócio.

## 3. Mapeamento da Lógica para Componentes

A tabela abaixo conecta as regras de negócio aos componentes de software que as implementam.

| Regra de Negócio | Componente Implementador | Detalhes da Implementação |
| :--- | :--- | :--- |
| **Cálculo de Saldo** | `App\Services\CotaService`<br>`Pessoa::getSaldoAttribute` | O cálculo `(Cota + Créditos - Débitos)` é centralizado no método `calcularSaldo` do Service. O Model `Pessoa` expõe isso via Accessor (`$pessoa->saldo`). |
| **Cota Base vs. Especial** | `CotaService::getCotaBase`<br>`CotaService::getCotaEspecial` | O Service arbitra a prioridade: se existir registro em `CotaEspecial`, ele prevalece sobre a cota calculada pelos vínculos (tabela `Cota`). |
| **Validação de Lançamento** | **Livewire Component**<br>`App\Livewire\Lancamento\ManageLancamentos` | A validação de entrada (valor positivo, tipo válido) é feita via **Atributos PHP** (`#[Rule]`) diretamente nas propriedades do componente Livewire, eliminando a necessidade de FormRequests externos para esta ação. |
| **Busca e Importação (Replicado)** | `App\Services\ReplicadoService` | Isola as queries SQL complexas ao banco legado/externo. O método `buscarPessoa` orquestra a busca local vs. remota e a sincronização dos dados para a tabela `pessoas`. |
| **Gestão de Acessos (ACL)** | `Spatie\Permission` e `Filament User Resource` | O controle de quem é `ADM` ou `OPR` é gerido pelo pacote Spatie, com interface administrativa provida pelo `UserResource` e `RoleResource` do Filament. |
| **Visualização de Extrato** | `App\Filament\Resources\ExtratoResource` | Recurso do Filament que fornece uma view (co-optando `Lancamento`) para listagem, filtro e exportação do histórico de lançamentos. |

## 4. Estrutura de Diretórios Chave

*   `app/Services/`: Contém `CotaService` e `ReplicadoService`.
*   `app/Models/`: Contém as entidades de domínio (`Pessoa`, `Lancamento`, `Cota`, `CotaEspecial`) e configuração de Auditoria.
*   `app/Livewire/Lancamento/`: Contém o componente `ManageLancamentos` (Lógica de UI e Validação da Operação).
*   `app/Filament/Resources/`: Contém os CRUDs administrativos (`User`, `Role`, `Audit`, `Cota`, `Extrato`).

## 5. Conclusão

Esta arquitetura favorece a manutenção ao separar claramente a **regra** (Services), a **interface** (Filament/Livewire) e os **dados** (Models). A escolha de validar regras de interface diretamente no componente Livewire simplifica o fluxo de feedback para o usuário, enquanto as regras de negócio estruturais permanecem protegidas na camada de Serviços.
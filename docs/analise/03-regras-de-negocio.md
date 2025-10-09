### **Regras de Negócio do Sistema CotaG**

#### **1. Introdução**
Este documento formaliza as regras de negócio que governam a operação do sistema CotaG. As regras aqui descritas foram extraídas e validadas a partir da análise do código-fonte Java do projeto.

#### **2. Visão Geral das Regras**
O sistema opera com base em três pilares funcionais, cujas regras principais são resumidas abaixo:

| Funcionalidade | Regra Principal | Implicação Prática |
| :--- | :--- | :--- |
| **Gestão de Cotas** | A Cota Especial tem prioridade absoluta. Na ausência dela, aplica-se a cota regular de maior valor baseada nos vínculos do usuário. | O sistema sempre garante o maior benefício de cota possível para o usuário, com flexibilidade para casos excepcionais. |
| **Transações e Saldo** | O saldo é calculado dinamicamente para o mês corrente. O sistema não impede que o saldo se torne negativo. | O controle de uso excessivo é uma responsabilidade administrativa do operador, não uma trava do sistema. O saldo é sempre atualizado em tempo real. |
| **Ciclo Mensal** | A cota é "renovada" implicitamente no início de cada mês, pois o cálculo de saldo passa a considerar apenas os lançamentos do novo período. | Não existe um processo noturno ou agendado para "resetar" os saldos. O sistema é inerentemente mensal por design de suas consultas. |

---

### 3. Regras Detalhadas por Módulo Funcional

#### **3.1. Módulo de Gestão de Cotas**
Este módulo define como a cota base de um usuário é estabelecida no início de cada ciclo mensal.

* **Regra 3.1.1: Precedência da Cota Especial**
    * **Descrição:** Uma cota especial atribuída a uma pessoa (`PESSOA`) tem prioridade máxima sobre qualquer cota regular derivada de seus vínculos.
    * **Mecanismo de Implementação:** No método `calcularCota()` da classe `MbLancamento`, o sistema primeiro executa uma consulta através do `daoCotaEspecial.buscarPorCodpes()`. Se um registro de cota especial for encontrado para o `codpes` do usuário, seu valor é imediatamente definido como a cota base.
    * **Cenário de Exemplo:** Um usuário é `ALUNOPOS`, cuja cota regular é de 80 cópias. No entanto, ele possui uma `COTA_ESPECIAL` registrada com o valor de 500. Para todos os efeitos, a cota base deste usuário para o cálculo do saldo mensal será **500**.

* **Regra 3.1.2: Determinação da Cota Regular por Vínculo de Maior Valor**
    * **Descrição:** Se um usuário não possui uma Cota Especial, sua cota base é determinada pelo vínculo (`VINCULO`) que lhe concede o maior número de cópias.
    * **Mecanismo de Implementação:** Caso a consulta por cota especial não retorne resultados, o método `calcularCota()` itera sobre a lista de vínculos da pessoa. Para cada vínculo, ele compara o valor da cota correspondente na tabela `COTA` e armazena sempre o maior valor encontrado.
    * **Cenário de Exemplo:** Um usuário possui dois vínculos ativos: `SERVIDOR` (cota padrão de 0) e `ALUNOPOS` (cota padrão de 80). O sistema irá atribuir a ele a cota base de **80**, garantindo o maior benefício.

#### **3.2. Módulo de Transações e Saldo**
Este módulo governa como as transações são processadas e como o saldo do usuário é afetado.

* **Regra 3.2.1: Cálculo Dinâmico do Saldo Mensal**
    * **Descrição:** O saldo de um usuário não é um valor fixo armazenado no banco de dados. Ele é sempre calculado em tempo real, considerando a cota base e todos os lançamentos (créditos e débitos) realizados dentro do mês e ano correntes.
    * **Mecanismo de Implementação:** A lógica reside no método `getSaldo()` da classe `Pessoa`. Ele inicializa uma variável com o valor da cota base e, em seguida, itera sobre a lista de lançamentos do mês, somando os créditos e subtraindo os débitos para chegar ao valor final. A lista de lançamentos é obtida através do método `daoLancamento.buscarLancamentosMesAtual()`, que filtra as transações por mês e ano.
    * **Implicação:** Esta abordagem garante que o saldo exibido é sempre preciso e reflete instantaneamente qualquer novo lançamento, sem a necessidade de rotinas de atualização.

* **Regra 3.2.2: Validação de Lançamentos e Permissão de Saldo Negativo**
    * **Descrição:** O sistema não impõe uma restrição que impeça um débito de exceder o saldo disponível. A validação de um lançamento é puramente estrutural.
    * **Mecanismo de Implementação:** Os métodos `debito()` e `credito()` na classe `MbLancamento` focam em criar e persistir um objeto `Lancamento`. Não há uma lógica condicional (`if (saldo >= quantidade)`) que verifique o saldo antes de registrar um débito.
    * **Implicação Prática:** A responsabilidade de gerenciar o uso das cotas recai sobre o operador da gráfica. O sistema atua como um livro de registros fiel, permitindo que saldos se tornem negativos. Um saldo negativo é um indicador para uma ação administrativa (como cobrança ou notificação ao usuário), mas não um bloqueio de sistema.

#### **3.3. Módulo de Ciclo Mensal**
Este módulo descreve o comportamento do sistema na transição entre os meses.

* **Regra 3.3.1: Renovação Mensal Implícita da Cota**
    * **Descrição:** O saldo de um usuário é efetivamente "renovado" no primeiro dia de cada novo mês. Este não é um evento ativo (como um script de reset), mas uma consequência natural da lógica de cálculo de saldo.
    * **Mecanismo de Implementação:** A consulta no método `buscarLancamentosMesAtual()` contém as cláusulas `WHERE MONTH(data) = :mes AND YEAR(data) = :ano`. No momento em que o mês muda (ex: de 31 de outubro para 1º de novembro), esta consulta passa a retornar uma lista vazia de lançamentos para o novo mês.
    * **Cenário de Exemplo:** Um usuário termina o mês de outubro com um saldo de -50 (gastou 50 cópias além de sua cota). No dia 1º de novembro, ao consultar seu saldo, o sistema não encontrará nenhum lançamento para novembro. Portanto, o cálculo do `getSaldo()` será `Cota Base - 0 + 0`, e seu saldo disponível será igual à sua cota base completa (ex: 200, se for um docente), pronto para ser usado no novo mês. Os lançamentos de outubro permanecem no banco para histórico.
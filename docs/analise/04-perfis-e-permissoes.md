### **Perfis de Acesso e Permissões – Sistema CotaG**

#### **1. Introdução**
Este documento formaliza a arquitetura de acesso e as regras de negócio associadas aos diferentes perfis de usuário no sistema CotaG. O seu propósito é servir como um guia conceitual definitivo para a equipe de desenvolvimento, detalhando o escopo de ação de cada ator e o comportamento esperado do sistema em relação às permissões.

#### **2. Atores do Sistema**
O ecossistema do CotaG envolve três atores distintos, com papéis e formas de interação fundamentalmente diferentes.

* **2.1. Usuário Final (representado pela entidade `PESSOA`)**
    Este é o beneficiário do serviço de cópias (ex: alunos, professores, servidores). É crucial entender que este ator **não interage diretamente com o sistema**. Ele não possui login, senha ou acesso à interface web. A sua interação é puramente física: ele solicita um serviço na gráfica, e um operador utiliza o CotaG para gerenciar sua cota em seu nome. Dentro do sistema, ele é uma entidade de dados, não um usuário com credenciais.

* **2.2. Operador (Perfil `OPR`)**
    Este é o usuário principal do sistema, tipicamente um funcionário da gráfica. Ele possui credenciais de acesso e utiliza a interface do CotaG para executar as operações do dia a dia, servindo como a ponte entre o Usuário Final e o registro digital de suas cotas.

* **2.3. Administrador (Perfil `ADM`)**
    Este é um usuário com privilégios elevados, responsável pela manutenção do sistema, segurança e gerenciamento das contas dos Operadores. Ele herda todas as capacidades de um Operador e possui acesso a áreas administrativas restritas.

#### **3. Detalhamento dos Perfis de Acesso (Roles)**

A seguir, uma descrição detalhada das responsabilidades e permissões de cada perfil que possui acesso direto ao sistema.

##### **3.1. Perfil: Operador (OPR)**
O Operador é o coração funcional do sistema. Suas permissões garantem a execução de todas as tarefas rotineiras de gerenciamento de cotas e impressões.

* **Módulo: Lançamentos e Consulta de Saldo**
    * **Consulta de Pessoas e Saldos:** O Operador tem a capacidade de buscar qualquer Usuário Final cadastrado na base de dados (via Nº USP, nome ou e-mail). Ao selecionar uma pessoa, o sistema exibe uma visão completa de seu status atual, incluindo o saldo de cópias disponíveis, a cota base para o mês e seus vínculos com a instituição. Esta é a ação inicial para qualquer transação.
    * **Registro de Débito (Uso de Cópias):** Esta é a permissão mais crítica e frequentemente usada. Permite ao Operador registrar o uso de serviços de impressão por um Usuário Final. A ação consiste em informar a quantidade de cópias utilizadas, que o sistema então registra como um `LANCAMENTO` do tipo `DEBITO`. Esta operação subtrai o valor do saldo do usuário e, crucialmente, **não é bloqueada por saldo insuficiente**, permitindo a existência de saldos negativos.
    * **Registro de Crédito (Adição de Saldo):** Permite ao Operador adicionar cópias ao saldo de um Usuário Final. Esta ação é utilizada em cenários como a compra de créditos adicionais ou concessões administrativas. O sistema registra um `LANCAMENTO` do tipo `CREDITO`, somando o valor ao saldo do usuário.

* **Módulo: Gestão de Cotas**
    * **Gerenciamento de Cotas Regulares:** O Operador tem autonomia total sobre as regras que definem as cotas padrão. Ele pode criar, visualizar, editar e excluir as associações entre um `vinculo` (ex: `DOCENTE`) e uma quantidade de cópias. Isso permite que a política de cotas da instituição seja ajustada diretamente pelo pessoal da gráfica, sem necessidade de intervenção técnica.
    * **Gerenciamento de Cotas Especiais:** O Operador pode gerenciar exceções à regra padrão. Ele tem permissão para criar, editar ou remover uma `COTA_ESPECIAL` para um indivíduo específico, garantindo flexibilidade para casos extraordinários (ex: um projeto de pesquisa que demanda mais cópias).

* **Módulo: Relatórios e Auditoria**
    * **Acesso ao Extrato Geral:** O Operador pode visualizar um histórico completo de todos os lançamentos realizados no sistema, para todos os usuários. Esta funcionalidade é essencial para auditorias, resolução de discrepâncias e geração de relatórios de uso.

##### **3.2. Perfil: Administrador (ADM)**
O Administrador possui uma visão global e controle total sobre o sistema. Sua função é garantir a segurança, a integridade e a correta administração da plataforma.

* **Hierarquia de Permissões**
    * O perfil de Administrador **herda todas as permissões do perfil de Operador**. Tudo o que um Operador pode fazer, um Administrador também pode.

* **Permissões Exclusivas do Administrador**
    * **Módulo: Gestão de Acesso ao Sistema**
        * **Gerenciamento de Usuários (Operadores):** O Administrador tem controle total sobre quem pode acessar o sistema CotaG. Ele pode criar novas contas de Operador ou Administrador, editar seus dados (nome, e-mail), desativar contas e remover usuários permanentemente.
        * **Redefinição de Senha:** Possui a capacidade de gerar uma nova senha para qualquer usuário do sistema, uma função crítica para a manutenção da segurança.
        * **Gerenciamento de Papéis de Usuários:** O Administrador pode atribuir ou remover perfis (`ADM`, `OPR`) de qualquer conta de usuário, controlando assim o nível de acesso de cada um.
        * **Gerenciamento de Perfis (Papéis):** Além de atribuir papéis, o Administrador pode gerenciar os próprios papéis, podendo criar novas categorias de acesso ou remover as existentes, caso a política da instituição mude.

    * **Módulo: Auditoria e Manutenção do Sistema**
        * **Acesso aos Logs do Sistema:** O Administrador é o único perfil com acesso à tabela de `LOG`. Esta permissão de leitura permite monitorar todas as ações significativas realizadas no sistema (logins bem-sucedidos e falhos, operações críticas, etc.), sendo uma ferramenta fundamental para auditoria de segurança e diagnóstico de problemas.
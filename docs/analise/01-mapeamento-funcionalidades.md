# Documentação Técnica Detalhada – Sistema Cotag

### Perfis de Acesso

* **Operador (`OPR`):** Realiza as operações diárias de débito e crédito de cópias.
* **Administrador (`ADM`):** Possui acesso total, incluindo a gestão de usuários e configurações do sistema.

---

### 1. Autenticação de Usuário

* **Perfis de Acesso:** Operador, Administrador.
* **Descrição Detalhada:** O sistema permite a autenticação de duas maneiras. A primeira é um **Login Local**, onde o usuário informa seu Número USP e uma senha específica do sistema. A segunda é via **Senha Única da USP**, que utiliza o fluxo OAuth para validar as credenciais do usuário diretamente com o sistema corporativo da USP. Ambas as tentativas de login, bem-sucedidas ou falhas, são gravadas na tabela `LOG`. Após a autenticação, o sistema verifica se o usuário possui os papéis (`PAPEL`) necessários para autorizar o acesso.
* **Componentes Envolvidos:**
    * **Interface:** `public/login.xhtml`, `public/callback.xhtml`
    * **Controlador:** `Auth.java`
    * **Modelos/Entidades:** `Usuario.java`, `Papel.java`, `Log.java`
    * **Acesso a Dados:** `DaoUsuario.java`, `DaoPapel.java`, `DaoLog.java`
    * **Tabelas do Banco de Dados:** `USUARIO`, `PAPEL`, `USUARIO_PAPEL`, `LOG`.
* **Regras de Negócio / Lógica de Implementação:**
    1.  **Login Local:**
        * O usuário fornece N° USP (`codlog`) e senha (`senha`).
        * O sistema busca o `USUARIO` pelo `codpes`.
        * Se o usuário for encontrado, o sistema gera um hash `sha256` da senha fornecida concatenada com o `salt` armazenado no banco.
        * O hash gerado é comparado com a `senha` armazenada no banco.
        * Em caso de sucesso, o usuário é autenticado, e uma entrada de 'AUTENTICACAO LOCAL' com status 'OK' é criada na tabela `LOG`. Caso contrário, o status é 'NEGADO'.
    2.  **Login USP (OAuth):**
        * O sistema redireciona o usuário para a URL de autorização da USP.
        * Após o login na USP, o usuário é redirecionado de volta para a URL de `callback` do sistema.
        * O sistema usa a biblioteca `scribe` para obter o `accessToken` e buscar os dados do usuário, incluindo o `codpes`.
        * Com o `codpes`, o sistema busca o registro correspondente na tabela local `USUARIO`.
        * Em caso de sucesso, o usuário é autenticado, e uma entrada de 'AUTENTICACAO USP' com status 'OK' é criada na tabela `LOG`.
    3.  **Autorização:**
        * Após a autenticação, o sistema verifica os `PAPEL`s associados ao `USUARIO`.
        * Se o usuário possuir um papel válido (`ADM` ou `OPR`), o acesso é concedido e uma entrada de 'AUTORIZACAO' com status 'OK' é criada na tabela `LOG`.

---

### 2. Controle de Lançamentos (Débito e Crédito)

* **Perfis de Acesso:** Operador, Administrador.
* **Descrição Detalhada:** É a principal funcionalidade do sistema. Permite que um operador registre o consumo (débito) ou a adição (crédito) de cópias para um cliente da gráfica. O processo se inicia com a busca do cliente pelo N° USP, nome completo ou e-mail. Após a identificação, o sistema exibe o perfil do cliente com seu saldo atualizado e o histórico de transações do mês, permitindo que o operador efetue o lançamento.
* **Componentes Envolvidos:**
    * **Interface:** `lancamento.xhtml`
    * **Controlador:** `MbLancamento.java`
    * **Acesso a Dados:** `DaoLancamento.java`, `DaoPessoa.java`, `DaoReplicado.java`
    * **Modelos/Entidades:** `Lancamento.java`, `Pessoa.java`, `Usuario.java`
    * **Tabelas do Banco de Dados:** `LANCAMENTO`, `PESSOA`, `VINCULO`.
* **Regras de Negócio / Lógica de Implementação:**
    1.  **Busca do Cliente:** O operador insere um critério de busca (`txtCriterioBusca`).
        * Se o critério for um número (`IntegerUtil.isInt`), o sistema busca no banco Replicado pelo N° USP (`DaoReplicado.buscarPessoaPorCodpes`).
        * Se contiver "@", busca por e-mail (`DaoReplicado.buscarPessoaPorEmail`).
        * Caso contrário, busca por nome (`DaoReplicado.buscarPessoasPorNome`).
    2.  **Importação de Dados:** Se a pessoa é encontrada no Replicado, seus dados (`codpes`, `nompes`, `vinculos`) são salvos ou atualizados na tabela local `PESSOA`.
    3.  **Cálculo de Saldo:** O sistema executa a lógica de "Cálculo de Saldo e Cota" (descrita na funcionalidade 3) e exibe o saldo e a cota na tela.
    4.  **Registro de Lançamento:** O operador informa a quantidade de cópias e clica em "Débito" ou "Crédito".
        * Uma nova entrada é criada na tabela `LANCAMENTO`.
        * O campo `tipoLancamento` é definido como `1` para Débito ou `0` para Crédito.
        * Os campos `valor` (quantidade), `data`, `pessoa_codpes` (cliente) e `usuario_id` (operador logado) são preenchidos.
    5.  **Atualização da Tela:** A tela é atualizada para refletir o novo saldo e o lançamento recém-adicionado no histórico do mês.

---

### 3. Cálculo de Saldo e Cota Mensal

* **Perfis de Acesso:** Operador, Administrador (executado automaticamente ao buscar um cliente).
* **Descrição Detalhada:** Lógica automática para determinar a quantidade de cópias que um cliente pode utilizar. O sistema primeiro verifica se há uma cota especial definida para aquele indivíduo. Caso não haja, ele determina a maior cota padrão com base nos vínculos ativos da pessoa com a USP. O saldo final é o resultado da cota calculada, somada aos créditos e subtraída dos débitos do mês.
* **Componentes Envolvidos:**
    * **Controlador:** `MbLancamento.java` (métodos `calcularCota` e `getSaldo` em `Pessoa`)
    * **Acesso a Dados:** `DaoCotaEspecial.java`, `DaoCota.java`, `DaoLancamento.java`
    * **Modelos/Entidades:** `Pessoa.java`, `Cota.java`, `CotaEspecial.java`
    * **Tabelas do Banco de Dados:** `COTA`, `COTA_ESPECIAL`, `VINCULO`, `LANCAMENTO`.
* **Regras de Negócio / Lógica de Implementação:**
    1.  **Verificar Cota Especial:** O sistema consulta a tabela `COTA_ESPECIAL` buscando pelo `codpes` do cliente (`DaoCotaEspecial.buscarPorCodpes`). Se um registro for encontrado, seu `valor` é definido como a cota base da pessoa.
    2.  **Verificar Cota Regular (se não houver especial):**
        * Se não houver cota especial, o sistema obtém todos os `vinculos` da pessoa da tabela `VINCULO`.
        * Para cada vínculo (ex: 'DOCENTE', 'ALUNOPOS'), ele consulta o `valor` correspondente na tabela `COTA`.
        * A cota base da pessoa será o maior `valor` encontrado entre todos os seus vínculos.
    3.  **Calcular Saldo Final:**
        * O sistema busca todos os `LANCAMENTO`s do cliente no mês e ano atuais (`DaoLancamento.buscarLancamentosMesAtual`).
        * O saldo final é calculado pela fórmula: `Saldo = Cota Base + SOMA(Créditos do mês) - SOMA(Débitos do mês)`.

---

### 4. Gestão de Cotas (Regulares e Especiais)

* **Perfis de Acesso:** Operador, Administrador.
* **Descrição Detalhada:** Permite a manutenção das regras de cotas. A **Gestão de Cotas Regulares** lista as cotas por vínculo (ex: 'SERVIDOR', 0 cópias) e permite editar o valor ou criar novas. A **Gestão de Cotas Especiais** permite criar uma exceção para um indivíduo, informando seu N° USP e o valor da cota.
* **Componentes Envolvidos:**
    * **Interface:** `cota.xhtml`, `cota_especial.xhtml`
    * **Controlador:** `MbCota.java`, `MbCotaEspecial.java`
    * **Acesso a Dados:** `DaoCota.java`, `DaoCotaEspecial.java`
    * **Modelos/Entidades:** `Cota.java`, `CotaEspecial.java`
    * **Tabelas do Banco de Dados:** `COTA`, `COTA_ESPECIAL`.
* **Regras de Negócio / Lógica de Implementação:**
    * A funcionalidade consiste em operações de CRUD (Criar, Ler, Atualizar, Deletar) sobre as tabelas `COTA` e `COTA_ESPECIAL`.
    * Para criar uma cota especial, o sistema primeiro valida se o N° USP informado corresponde a uma pessoa existente no Replicado.

---

### 5. Gestão de Usuários e Papéis

* **Perfis de Acesso:** Administrador.
* **Descrição Detalhada:** Área restrita ao administrador para gerenciar quem pode acessar o sistema. O administrador pode criar novos usuários, editar seus dados (nome, e-mail), resetar suas senhas e atribuir papéis que definem suas permissões.
* **Componentes Envolvidos:**
    * **Interface:** `usuario.xhtml`, `papel.xhtml`
    * **Controlador:** `MbUsuario.java`, `MbPapel.java`
    * **Acesso a Dados:** `DaoUsuario.java`, `DaoPapel.java`
    * **Modelos/Entidades:** `Usuario.java`, `Papel.java`
    * **Tabelas do Banco de Dados:** `USUARIO`, `PAPEL`, `USUARIO_PAPEL`.
* **Regras de Negócio / Lógica de Implementação:**
    * **Criação de Usuário:** O administrador informa N° USP, nome e e-mail. O sistema gera um `salt` e uma senha aleatória de 8 caracteres. A senha é criptografada com `sha256` e salva. A senha original (não criptografada) é exibida na tela para que o administrador a repasse ao novo usuário.
    * **Reset de Senha:** A mesma lógica de geração de senha e salt da criação é aplicada para resetar uma senha existente.
    * **Gestão de Papéis:** O administrador pode associar ou desassociar papéis (listados da tabela `PAPEL`) a um usuário, o que cria ou remove registros na tabela de junção `USUARIO_PAPEL`.
    * A tela de "Papéis" permite o CRUD simples dos registros na tabela `PAPEL`.

---

### 6. Consulta de Extrato de Lançamentos

* **Perfis de Acesso:** Operador, Administrador.
* **Descrição Detalhada:** Esta funcionalidade permite aos usuários autorizados visualizar um histórico completo de todas as transações de cota de impressão. Ela fornece duas visualizações principais: uma exibindo apenas as transações do mês atual e outra mostrando o histórico completo. A lista inclui detalhes como a data da transação, a pessoa envolvida, o tipo de transação (crédito ou débito) e a quantidade.
* **Componentes Envolvidos:**
    * **Interface:** `extrato.xhtml`
    * **Controlador:** `MbExtrato.java`
    * **Acesso a Dados:** `DaoLancamento.java`
    * **Modelos/Entidades:** `Lancamento.java`
    * **Tabelas do Banco de Dados:** `LANCAMENTO`.
* **Regras de Negócio / Lógica de Implementação:**
    1.  O usuário navega para a página "Extrato".
    2.  A página apresenta os botões "Lançamentos do mês atual" e "Todos os lançamentos".
    3.  Clicar em "Lançamentos do mês atual" aciona o método `carregarLancamentosMes`, que chama `daoLancamento.buscarLancamentosMesAtual()`. Este método executa uma consulta na tabela `LANCAMENTO` filtrando registros onde o mês e ano da coluna `data` correspondem à data atual.
    4.  Clicar em "Todos os lançamentos" aciona o método `carregar`, que chama `daoLancamento.findAll()` para obter todos os registros da tabela `LANCAMENTO`.
    5.  A lista de `Lancamento` resultante é exibida em uma tabela, mostrando data, pessoa, tipo e valor para cada registro.

---

### 7. Visualização de Logs do Sistema

* **Perfis de Acesso:** Administrador.
* **Descrição Detalhada:** Funcionalidade exclusiva para administradores que fornece uma trilha de auditoria dos eventos do sistema. Exibe uma tabela paginada e filtrável de todos os logs, permitindo o monitoramento de padrões de acesso, tentativas de login e outras operações para fins de segurança e diagnóstico.
* **Componentes Envolvidos:**
    * **Interface:** `log.xhtml`
    * **Controlador:** `MbLog.java`
    * **Acesso a Dados:** `DaoLog.java`
    * **Modelos/Entidades:** `Log.java`, `Usuario.java`
    * **Tabelas do Banco de Dados:** `LOG`.
* **Regras de Negócio / Lógica de Implementação:**
    1.  Antes da renderização da página, o método `carregar()` em `MbLog.java` é invocado.
    2.  Este método chama `daoLog.findAll()` para obter todos os registros da tabela `LOG`.
    3.  A lista de logs é ordenada em ordem cronológica decrescente por meio do `OrdenadorLog`, que compara o `timestamp` de cada entrada.
    4.  Os dados são apresentados em uma tabela paginada e com filtros nas colunas, permitindo ao administrador pesquisar por eventos ou usuários específicos.

---

### 8. Recuperação de Senha (Login Local)

* **Perfis de Acesso:** Operador, Administrador (enquanto usuários do sistema).
* **Descrição Detalhada:** Mecanismo de autoatendimento para usuários que esqueceram sua senha local. O usuário solicita a redefinição fornecendo seu N° USP e e-mail. O sistema valida os dados e envia um link com um token único e com tempo de validade para o e-mail do usuário, permitindo que ele cadastre uma nova senha.
* **Componentes Envolvidos:**
    * **Interface:** `public/requisicao_senha.xhtml`, `public/nova_senha.xhtml`
    * **Controlador:** `MbRequisicaoSenha.java` (implícito)
    * **Acesso a Dados:** `DaoRequisicaoSenha.java`, `DaoUsuario.java`
    * **Modelos/Entidades:** `RequisicaoSenha.java`, `Usuario.java`
    * **Tabelas do Banco de Dados:** `REQUISICAO_SENHA`, `USUARIO`.
* **Regras de Negócio / Lógica de Implementação:**
    1.  **Solicitação:**
        * Na tela `requisicao_senha.xhtml`, o usuário informa seu N° USP e e-mail.
        * O método `gerarPedido` valida se os dados correspondem a um `USUARIO` existente.
        * Se positivo, um registro é criado na tabela `REQUISICAO_SENHA` com um `token` único, data de solicitação, data de validade e a flag `ativa` como `true`.
    2.  **Redefinição:**
        * O usuário clica no link recebido e é direcionado para a página `nova_senha.xhtml`.
        * O método `pedidoValido` verifica se o token na URL é válido (existe, está ativo e não expirou).
        * Se o token for válido, o usuário define uma nova senha. O método `salvarNovaSenha` gera um novo `salt`, cria um hash `sha256` da nova senha e atualiza os dados na tabela `USUARIO`.
        * A flag `ativa` na tabela `REQUISICAO_SENHA` é definida como `false` para invalidar o token.

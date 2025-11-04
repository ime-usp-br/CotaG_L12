## Summary
Este PR refatora a funcionalidade de lançamento para utilizar um modal, adiciona paginação ao histórico de lançamentos e corrige o cálculo do saldo.

## Changes Made
- Adiciona modal para lançamento de débitos e créditos.
- Adiciona paginação para o histórico de lançamentos.
- Corrige o cálculo do saldo para considerar os créditos.
- Adiciona `fillable` ao modelo `Lancamento`.
- Remove ação de visualização da tabela de extratos.
- Corrige o tipo do parâmetro nos métodos da CotaEspecialPolicy.
- Cria seeder para cotas.
- Usa updateOrCreate no DatabaseSeeder.

## Related Issues
Closes #51
Closes #52
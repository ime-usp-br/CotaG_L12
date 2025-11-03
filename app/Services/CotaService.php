<?php

namespace App\Services;

use App\Models\Cota;
use App\Models\Pessoa;
use Carbon\Carbon; // Importamos o Carbon para lidar com datas
use Illuminate\Database\Eloquent\Builder;

/**
 * Encapsula todas as regras de negócio para cálculo
 * de cotas e saldo de impressões de uma Pessoa.
 */
class CotaService
{
    /**
     * Calcula o saldo de impressões restante para uma pessoa no mês corrente.
     *
     * O cálculo segue a lógica: (Cota Base - Total de Lançamentos do Mês)
     *
     * @param Pessoa $pessoa O objeto Pessoa para o qual o saldo será calculado.
     * @return int O saldo final de impressões.
     */
    public function calcularSaldo(Pessoa $pessoa): int
    {
        $cotaBase = $this->getCotaBase($pessoa);
        $totalLancamentos = $this->getTotalLancamentosMes($pessoa);

        // Critério 4: Calcular o saldo final
        return $cotaBase - $totalLancamentos;
    }

    /**
     * Obtém a cota base de impressões para a pessoa.
     *
     * A lógica segue a ordem de prioridade:
     * 1. Valor da CotaEspecial, se existir.
     * 2. Valor máximo das Cotas padrão associadas aos Vínculos da pessoa.
     * 3. Retorna 0 se nenhuma cota for encontrada.
     *
     * @param Pessoa $pessoa
     * @return int O valor da cota base.
     */
    public function getCotaBase(Pessoa $pessoa): int
    {
        // Critério 3.1: Verificar se existe uma CotaEspecial ativa
        // Usamos o relacionamento 'cotaEspecial' que definimos no model Pessoa
        $cotaEspecial = $pessoa->cotaEspecial;

        if ($cotaEspecial) {
            return (int) $cotaEspecial->valor;
        }

        // Critério 3.2: Buscar a maior Cota padrão com base nos Vínculos
        // Usamos o relacionamento 'vinculos' que definimos no model Pessoa
        
        // 1. Pegamos todos os 'tipo_vinculo' da pessoa (ex: 'ALUNO', 'SERVIDOR')
        $tiposDeVinculo = $pessoa->vinculos->pluck('tipo_vinculo');

        // Se a pessoa não tiver vínculos, não tem cota padrão.
        if ($tiposDeVinculo->isEmpty()) {
            return 0; // Cumpre o Critério 5
        }

        // 2. Buscamos na tabela 'cotas' qual é o maior valor
        //    entre os tipos de vínculo que a pessoa possui.
        $cotaMaxima = Cota::whereIn('tipo_vinculo', $tiposDeVinculo)->max('valor');

        return (int) $cotaMaxima; // (int) de null é 0, o que também cumpre o Critério 5
    }

    /**
     * Obtém a soma de todos os lançamentos (débitos) da pessoa no mês corrente.
     *
     * @param Pessoa $pessoa
     * @return int O total de impressões gastas no mês.
     */
    private function getTotalLancamentosMes(Pessoa $pessoa): int
    {
        // Refatorado para usar o Query Scope.
        // A lógica de 'whereBetween' foi movida para o model Lancamento.
        $total = $pessoa->lancamentos()
            ->where('tipo_lancamento', 1) // Filtra apenas por débitos
            ->mesAtual()                 
            ->sum('valor');

        return (int) $total;
    }
}
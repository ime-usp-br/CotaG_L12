<?php

namespace App\Services;

use App\Models\Cota;
use App\Models\CotaEspecial;
use App\Models\Lancamento;
use App\Models\Pessoa;
use Carbon\Carbon; // Importamos o Carbon para lidar com datas

/**
 * Encapsula todas as regras de negócio para cálculo
 * de cotas e saldo de impressões de uma Pessoa.
 */
class CotaService
{
    /**
     * Ponto de entrada principal para registrar um débito ou crédito.
     *
     * @param  Pessoa  $pessoa  A pessoa que recebe o lançamento.
     * @param  int  $valor  O valor total do lançamento.
     * @param  int  $tipo  0 para Crédito, 1 para Débito.
     * @param  int  $operadorId  O ID do usuário logado que está fazendo a operação.
     */
    public function registrarDebitoOuCredito(Pessoa $pessoa, int $valor, int $tipo, int $operadorId): void
    {
        // Alteração: Não consumimos mais a CotaEspecial destrutivamente.
        // Apenas registramos o lançamento. O cálculo de saldo (calcularSaldo)
        // se encarregará de subtrair o total de débitos da cota (Fixa ou Especial).

        Lancamento::create([
            'data' => now(),
            'tipo_lancamento' => $tipo, // 0=Crédito, 1=Débito
            'valor' => $valor,
            'codigo_pessoa' => $pessoa->codigo_pessoa,
            'usuario_id' => $operadorId,
        ]);
    }

    /**
     * Calcula o saldo de impressões restante para uma pessoa no mês corrente.
     *
     * LÓGICA CORRIGIDA (AGENTE.md 3.1):
     * 1. Se tiver Cota Especial, usa ela.
     * 2. Se não, usa Cota Base (Máximo dos Vínculos).
     * 3. Soma Créditos e subtrai Débitos.
     *
     * @param  Pessoa  $pessoa  O objeto Pessoa para o qual o saldo será calculado.
     * @return int O saldo final de impressões.
     */
    public function calcularSaldo(Pessoa $pessoa): int
    {
        $cotaEspecial = $this->getCotaEspecial($pessoa);

        // Prioridade: Se existe cota especial (mesmo que 0?), usa ela.
        // Se retornar null, significa que não tem registro de cota especial.
        if ($cotaEspecial !== null) {
            $cotaMes = $cotaEspecial;
        } else {
            $cotaMes = $this->getCotaBase($pessoa);
        }

        $totalCreditos = $this->getCreditos($pessoa);
        $totalDebitos = $this->getTotalLancamentosMes($pessoa);

        // Saldo = Cota do Mês + Créditos - Débitos
        $saldo = ($cotaMes + $totalCreditos) - $totalDebitos;

        return (int) $saldo;
    }

    /**
     * Obtém a cota base de impressões para a pessoa (APENAS DO VÍNCULO).
     * 1. Valor máximo das Cotas padrão associadas aos Vínculos da pessoa.
     * 2. Retorna 0 se nenhuma cota for encontrada.
     *
     * @return int O valor da cota base.
     */
    public function getCotaBase(Pessoa $pessoa): int
    {
        $tiposDeVinculo = $pessoa->vinculos->pluck('tipo_vinculo');

        if ($tiposDeVinculo->isEmpty()) {
            return 0;
        }

        $cotaMaxima = Cota::whereIn('tipo_vinculo', $tiposDeVinculo)->max('valor');

        return (int) $cotaMaxima;
    }

    /**
     * Obtém o valor da Cota Especial, se houver.
     * Retorna null se não houver registro.
     */
    public function getCotaEspecial(Pessoa $pessoa): ?int
    {
        // Verifica se existe algum registro
        $existe = CotaEspecial::where('codigo_pessoa', $pessoa->codigo_pessoa)->exists();

        if (!$existe) {
            // Retorna null para indicar que deve usar Cota Base
            return null;
        }

        // Se existe, retorna a soma (caso haja multiplas, o que seria estranho, mas somamos).
        // Normalmente seria um único registro.
        return (int) CotaEspecial::where('codigo_pessoa', $pessoa->codigo_pessoa)->sum('valor');
    }

    /**
     * Obtém a soma de todos os lançamentos (débitos) da pessoa no mês corrente.
     *
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

    /**
     * Obtém a soma de todos os lançamentos (créditos) da pessoa no mês corrente.
     *
     * @return int O total de impressões gastas no mês.
     */
    private function getCreditos(Pessoa $pessoa): int
    {
        // Carrega a soma dos créditos (tipo 0) do mês atual
        $totalCreditos = $pessoa->lancamentos()
            ->where('tipo_lancamento', 0) // 0 = Crédito
            ->mesAtual() // Usa o scope
            ->sum('valor');

        return (int) $totalCreditos;
    }
}

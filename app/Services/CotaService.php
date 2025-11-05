<?php

namespace App\Services;

use App\Models\Cota;
use App\Models\Pessoa;
use App\Models\CotaEspecial;
use App\Models\Lancamento;
use Carbon\Carbon; // Importamos o Carbon para lidar com datas
use Illuminate\Database\Eloquent\Builder;

/**
 * Encapsula todas as regras de negócio para cálculo
 * de cotas e saldo de impressões de uma Pessoa.
 */
class CotaService
{
    /**
     * Ponto de entrada principal para registrar um débito ou crédito.
     *
     * @param Pessoa $pessoa A pessoa que recebe o lançamento.
     * @param integer $valor O valor total do lançamento.
     * @param integer $tipo 0 para Crédito, 1 para Débito.
     * @param integer $operadorId O ID do usuário logado que está fazendo a operação.
     */
    public function registrarDebitoOuCredito(Pessoa $pessoa, int $valor, int $tipo, int $operadorId): void
    {
        if ($tipo == 0) {
            // Se for CRÉDITO, apenas crie o lançamento.
            Lancamento::create([
                'data' => now(),
                'tipo_lancamento' => 0,
                'valor' => $valor,
                'codigo_pessoa' => $pessoa->codigo_pessoa,
                'usuario_id' => $operadorId,
            ]);
            return;
        }
        else {
            $this->consumirCotaEspecial($pessoa, $valor);
            
            Lancamento::create([
                'data' => now(),
                'tipo_lancamento' => 1,
                'valor' => $valor,
                'codigo_pessoa' => $pessoa->codigo_pessoa,
                'usuario_id' => $operadorId,
            ]);
        }
    }

    /**
     * Consome o valor de um débito das cotas especiais da pessoa (FIFO).
     * Atualiza ou deleta os registros de CotaEspecial no banco.
     *
     * @param Pessoa $pessoa
     * @param integer $valorDebito O valor total do débito.
     * @return integer O valor do débito que RESTOU após consumir as cotas.
     */
    private function consumirCotaEspecial(Pessoa $pessoa, int $valorDebito): int
    {
        $cotasEspeciais = CotaEspecial::where('codigo_pessoa', $pessoa->codigo_pessoa)
                                      ->orderBy('id', 'asc') // Pega a mais antiga primeiro
                                      ->get();

        $debitoRestante = $valorDebito;

        foreach ($cotasEspeciais as $cota) {
            if ($debitoRestante <= 0) {
                // O débito já foi totalmente coberto.
                break;
            }

            $valorNestaCota = $cota->valor;

            if ($debitoRestante >= $valorNestaCota) {
                // O débito (ex: 50) é maior que esta cota (ex: 10).
                // Consome a cota inteira.
                
                $debitoRestante -= $valorNestaCota; // Débito agora é 40
                $cota->delete(); // Deleta o registro da cota de 10

            } else {
                // O débito (ex: 5) é menor que esta cota (ex: 20).
                // Consome parte da cota.
                
                $cota->valor -= $debitoRestante; // Cota agora vale 15
                $cota->save(); // Atualiza o registro no banco

                $debitoRestante = 0; // O débito foi totalmente coberto.
            }
        }

        // Retorna o que sobrou do débito, que será descontado da Cota Base.
        return $debitoRestante;
    }


    /**
     * Calcula o saldo de impressões restante para uma pessoa no mês corrente.
     *
     * ESTA LÓGICA AGORA É SIMPLES:
     * (Cota Base + Cota Especial + Créditos) - Débitos da Cota Base
     *
     * @param Pessoa $pessoa O objeto Pessoa para o qual o saldo será calculado.
     * @return int O saldo final de impressões.
     */
    public function calcularSaldo(Pessoa $pessoa): int
    {
        $cotaBase = $this->getCotaBase($pessoa);
        $cotaEspecial = $this->getCotaEspecial($pessoa); // Pega o que sobrou
        $totalCreditos = $this->getCreditos($pessoa);
        $totalDebitos = $this->getTotalLancamentosMes($pessoa); // Pega só os débitos da cota base

        // O saldo é a soma de todas as cotas (Base + Especial + Créditos)
        // menos a soma de todos os débitos (que agora só afetam a base).
        $saldo = ($cotaBase + $cotaEspecial + $totalCreditos) - $totalDebitos;
        
        return (int) $saldo;
    }

    /**
     * Obtém a cota base de impressões para a pessoa (APENAS DO VÍNCULO).
     * 1. Valor máximo das Cotas padrão associadas aos Vínculos da pessoa.
     * 2. Retorna 0 se nenhuma cota for encontrada.
     *
     * @param Pessoa $pessoa
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
     * Obtém a SOMA de todas as cotas especiais de uma pessoa.
     *
     * @param Pessoa $pessoa
     * @return integer
     */
    public function getCotaEspecial(Pessoa $pessoa): int
    {
        $somaCotasEspeciais = CotaEspecial::where('codigo_pessoa', $pessoa->codigo_pessoa)
                                        ->sum('valor');

        return (int) $somaCotasEspeciais;
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

    /**
     * Obtém a soma de todos os lançamentos (créditos) da pessoa no mês corrente.
     *
     * @param Pessoa $pessoa
     * @return int O total de impressões gastas no mês.
     */
    private function getCreditos(Pessoa $pessoa) : int
    {
        // Carrega a soma dos créditos (tipo 0) do mês atual
        $totalCreditos = $pessoa->lancamentos()
            ->where('tipo_lancamento', 0) // 0 = Crédito
            ->mesAtual() // Usa o scope
            ->sum('valor');
        
            return (int) $totalCreditos;
    }
}
<?php

namespace App\Services;

use App\Exceptions\ReplicadoServiceException; // Import custom exception
use Illuminate\Support\Facades\Log;
use Uspdev\Replicado\Pessoa as ReplicadoPessoa;
use App\Models\Pessoa;
use App\Models\User;
use App\Models\Vinculo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection; 
use Illuminate\Support\Facades\DB;

/**
 * Classe de serviço para interagir com o banco de dados Replicado da USP.
 */
class ReplicadoService
{
    /**
     * Valida se o Número USP (codpes) e o e-mail fornecidos pertencem à mesma pessoa válida no Replicado.
     *
     * Este método consulta o Replicado para verificar a existência do `codpes`
     * e se o `email` fornecido está associado a esse `codpes`.
     *
     * @param  int  $codpes  O Número USP (NUSP).
     * @param  string  $email  O endereço de e-mail para validar em conjunto com o `codpes`.
     * @return bool Retorna `true` se o `codpes` e o `email` corresponderem a uma pessoa válida, `false` caso contrário.
     *
     * @throws \App\Exceptions\ReplicadoServiceException Se ocorrer um problema de comunicação com o banco de dados Replicado.
     */
    public function validarNuspEmail(int $codpes, string $email): bool
    {
        if (! str_ends_with(strtolower($email), 'usp.br')) {
            Log::warning("Replicado Validation: Attempt to validate non-USP email '{$email}' for codpes {$codpes}.");
            // Depending on strictness, this might be an early return false or even an exception.
            // For now, let it proceed to check against Replicado records.
        }

        try {
            $emailsPessoa = ReplicadoPessoa::emails($codpes);

            if (empty($emailsPessoa)) {
                Log::info("Replicado Validation: No person found or no emails registered for codpes {$codpes}.");

                return false;
            }

            foreach ($emailsPessoa as $emailCadastrado) {
                if (is_string($emailCadastrado) && (strtolower(trim($emailCadastrado)) === strtolower($email))) {
                    Log::info("Replicado Validation: Success for codpes {$codpes} and email '{$email}'.");

                    return true;
                }
            }

            Log::info("Replicado Validation: Email '{$email}' does not match registered emails for codpes {$codpes}.");

            return false;

        } catch (\Exception $e) {
            Log::error("Replicado Service Error: Failed validating codpes {$codpes} and email '{$email}'. Error: ".$e->getMessage(), ['exception' => $e]);
            // Re-throw as a custom, more specific exception for better handling by callers.
            throw new ReplicadoServiceException('Replicado service communication error while validating NUSP/email.', 0, $e);
        }
    }

    /**
     * Busca uma pessoa e seus vínculos ativos no Replicado usando o NUSP (codpes).
     *
     * Executa uma consulta SQL customizada
     * para encontrar uma pessoa com vínculo não-desativado ('sitatl' <> 'D').
     *
     * @param string $codpes O Número USP (código da pessoa) a ser buscado.
     * @return array{codpes: int, nompes: string, vinculos: string[]}|null Um array associativo com os dados da pessoa ou null se não for encontrada.
     * @throws ReplicadoServiceException Se houver uma falha na comunicação ou na consulta ao banco de dados Replicado.
     */
    public function buscarPessoaPorCodpes(string $codpes): ?array
    {
        $sql = "SELECT TOP(1) P.codpes, COALESCE(P.nomcnhpes, P.nompes) AS nompes 
                FROM PESSOA P 
                INNER JOIN VINCULOPESSOAUSP V ON (P.codpes = V.codpes) 
                WHERE V.sitatl <> 'D' 
                AND P.codpes = :codpes";

        try {
            $resultado = DB::connection('replicado')->select($sql, ['codpes' => $codpes]);

            if (!empty($resultado)) {
                $pessoa = [
                    'codpes' => $resultado[0]->codpes,
                    'nompes' => trim($resultado[0]->nompes) 
                ];
                $pessoa['vinculos'] = $this->buscarVinculos($codpes);
                return $pessoa;
            }
        } catch (\Exception $e) {
            Log::error('Falha ao buscar por CODPES no Replicado: ' . $e->getMessage());
            throw new ReplicadoServiceException('O serviço do Replicado falhou (CODPES).', 0, $e);
        }
        
        return null;
    }

    /**
     * Busca uma pessoa e seus vínculos ativos no Replicado usando o e-mail.
     *
     * Executa uma consulta SQL customizada
     * que junta PESSOA e EMAILPESSOA.
     *
     * @param string $email O e-mail a ser buscado.
     * @return array{codpes: int, nompes: string, vinculos: string[]}|null Um array associativo com os dados da pessoa ou null se não for encontrada.
     * @throws ReplicadoServiceException Se houver uma falha na comunicação ou na consulta ao banco de dados Replicado.
     */
    public function buscarPessoaPorEmail(string $email): ?array
    {
        $sql = "SELECT TOP(1) P.codpes, COALESCE(P.nomcnhpes, P.nompes) AS nompes 
                FROM PESSOA AS P 
                INNER JOIN EMAILPESSOA AS E ON (E.codpes = P.codpes) 
                WHERE E.codema = :email";
        
        try {
            $resultado = DB::connection('replicado')->select($sql, ['email' => $email]);

            if (!empty($resultado)) {
                $pessoa = [
                    'codpes' => $resultado[0]->codpes,
                    'nompes' => trim($resultado[0]->nompes)
                ];
                $pessoa['vinculos'] = $this->buscarVinculos((string)$pessoa['codpes']);
                return $pessoa;
            }
        } catch (\Exception $e) {
            Log::error('Falha ao buscar por EMAIL no Replicado: ' . $e->getMessage());
            throw new ReplicadoServiceException('O serviço do Replicado falhou (EMAIL).', 0, $e);
        }

        return null;
    }

    /**
     * Busca uma lista de pessoas e seus vínculos ativos no Replicado por nome (busca parcial).
     *
     * Executa uma consulta SQL customizada 
     * que trata espaços como wildcards (%).
     *
     * @param string $nome O critério de busca por nome (parcial).
     * @return array<int, array{codpes: int, nompes: string, vinculos: string[]}> Uma lista de arrays associativos. Retorna array vazio se nada for encontrado.
     * @throws ReplicadoServiceException Se houver uma falha na comunicação ou na consulta ao banco de dados Replicado.
     */
    public function buscarPessoasPorNome(string $nome): array
    {
        $nome = "%" . strtolower(trim($nome)) . "% ";
        $nome = str_replace(" ", "%", $nome);

        $sql = "SELECT P.codpes, COALESCE(P.nomcnhpes, P.nompes) AS nompes 
                FROM PESSOA AS P
                WHERE LOWER(nompes) LIKE :nome ORDER BY nompes ASC";
        
        $pessoas = [];
        try {
            $resultados = DB::connection('replicado')->select($sql, ['nome' => $nome]);
            
            if (!empty($resultados)) {
                foreach ($resultados as $obj) {
                    $pessoa = [
                        'codpes' => $obj->codpes,
                        'nompes' => trim($obj->nompes)
                    ];
                    $pessoa['vinculos'] = $this->buscarVinculos((string)$pessoa['codpes']);
                    $pessoas[] = $pessoa;
                }
            }
        } catch (\Exception $e) {
            Log::error('Falha ao buscar por NOME no Replicado: ' . $e->getMessage());
            throw new ReplicadoServiceException('O serviço do Replicado falhou (NOME).', 0, $e);
        }

        return $pessoas;
    }

    /**
     * Busca os vínculos ativos (sitatl <> 'D') de uma pessoa no Replicado.
     *
     * Este método executa a lógica de negócio:
     * 1. Busca em VINCULOPESSOAUSP por vínculos não-desativados.
     * 2. Aplica trim() aos resultados de 'tipvin' (para limpar espaços).
     * 3. Trata 'nomcaa' iniciado com 'Docente' como um vínculo 'DOCENTE'.
     * 4. Retorna uma lista de strings de vínculos únicos.
     *
     * @param string $codpes O Número USP (código da pessoa).
     * @return string[] Um array de strings com os tipos de vínculo (ex: 'ALUNOGR', 'DOCENTE'). Retorna array vazio se não encontrar.
     */
    public function buscarVinculos(string $codpes): array
    {
        $vinculos = [];
        $sql = "SELECT tipvin, nomcaa FROM VINCULOPESSOAUSP 
                WHERE sitatl <> 'D' AND codpes = :codpes";
        
        try {
            $resultados = DB::connection('replicado')->select($sql, ['codpes' => $codpes]);

            if (!empty($resultados)) {
                foreach ($resultados as $obj) {
                    $vinculo = trim($obj->tipvin);
                    $nomcaa = $obj->nomcaa ? trim($obj->nomcaa) : null;

                    if ($nomcaa && str_starts_with($nomcaa, 'Docente')) {
                        $vinculo = 'DOCENTE';
                    }
                    $vinculos[] = $vinculo;
                }
            }
        } catch (\Exception $e) {
            Log::error('Falha ao buscar VINCULOS no Replicado: ' . $e->getMessage());
        }

        return array_unique($vinculos); // Retorna vínculos limpos e únicos
    }
}

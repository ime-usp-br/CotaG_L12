<?php

namespace App\Services;

use App\Exceptions\ReplicadoServiceException; // Import custom exception
use Illuminate\Support\Facades\Log;
use Uspdev\Replicado\Pessoa;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

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
            $emailsPessoa = Pessoa::emails($codpes);

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
     * Busca um único registro de User com base em um critério.
     *
     * A busca é realizada na tabela 'users' e tenta encontrar
     * uma correspondência nos seguintes campos:
     * - 'codigo_pessoa' (busca exata)
     * - 'name' (busca parcial, "LIKE")
     * - 'email' (busca exata)
     *
     * @param string $criterio O valor a ser buscado (código, nome ou email).
     * @return User|null O objeto User encontrado ou null se nenhum resultado for encontrado.
     */
    public function buscarPessoa(string $criterio): ?User
    {
        // Inicia uma nova consulta no model User
        return User::query() // Buscando em User
            ->where(function (Builder $query) use ($criterio) {
                
                // Critério 1: Buscar por 'codigo_pessoa' (busca exata)
                $query->where('codigo_pessoa', $criterio);

                // Critério 2: Buscar por 'name' (busca parcial)
                $query->orWhere('name', 'like', '%' . $criterio . '%');

                // Critério 3: Buscar por 'email' (busca exata)
                $query->orWhere('email', $criterio);
            })
            // Pega o primeiro resultado
            ->first();
    }
}

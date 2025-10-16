<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Representa um lançamento financeiro.
 *
 * @property int $id A chave primária do lançamento.
 * @property Carbon $data A data e hora do lançamento.
 * @property int $tipo_lancamento O tipo do lançamento (0 = Crédito, 1 = Débito).
 * @property int $valor O valor do lançamento.
 * @property int $codigo_pessoa A chave estrangeira para a pessoa.
 * @property int $usuario_id A chave estrangeira para o usuário que registrou.
 * @property Carbon|null $created_at Timestamp de criação do registro.
 * @property Carbon|null $updated_at Timestamp da última atualização do registro.
 *
 * @property-read Pessoa $pessoa A pessoa associada a este lançamento.
 * @property-read User $usuario O usuário que registrou este lançamento.
 */
class Lancamento extends Model
{
    use HasFactory;

    /**
     * Pega a pessoa associada a este lançamento.
     *
     * @return BelongsTo
     */
    public function pessoa()
    {
        return $this->belongsTo(Pessoa::class, 'codigo_pessoa', 'codigo_pessoa');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

use OwenIt\Auditing\Contracts\Auditable;

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
 * @property-read Pessoa $pessoa A pessoa associada a este lançamento.
 * @property-read User $usuario O usuário que registrou este lançamento.
 */
class Lancamento extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    /**
     * Os atributos que podem ser preenchidos em massa.
     *
     * @var array
     */
    protected $fillable = [
        'data',
        'tipo_lancamento',
        'valor',
        'codigo_pessoa',
        'usuario_id',
    ];

    /**
     * Pega a pessoa associada a este lançamento.
     *
     * @return BelongsTo
     */
    public function pessoa()
    {
        return $this->belongsTo(Pessoa::class, 'codigo_pessoa', 'codigo_pessoa');
    }

    /**
     * Pega o usuário (do sistema) que registrou o lançamento.
     * (Make sure this method exists EXACTLY like this)
     */
    public function usuario(): BelongsTo // <-- MUST be named 'usuario' (lowercase 'u')
    {
        // Assumes 'usuario_id' column exists in 'lancamentos' table
        // Assumes User model's primary key is 'id'
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Limita a consulta para incluir apenas os lançamentos do mês e ano atuais.
     *
     * Este é um Query Scope do Eloquent, que pode ser usado como ->mesAtual().
     * A consulta usa a coluna 'data' para a filtragem.
     *
     * @param  Builder  $query  O construtor de consultas do Eloquent.
     */
    public function scopeMesAtual(Builder $query): Builder
    {
        return $query->whereYear('data', Carbon::now()->year)
            ->whereMonth('data', Carbon::now()->month);
    }
}

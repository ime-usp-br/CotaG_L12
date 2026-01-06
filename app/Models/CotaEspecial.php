<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Representa uma cota especial para uma pessoa.
 *
 * @property int $id A chave primária da cota especial.
 * @property int $codigo_pessoa A chave estrangeira que liga à pessoa.
 * @property int $valor O valor da cota especial.
 * @property Carbon|null $created_at Timestamp de criação do registro.
 * @property Carbon|null $updated_at Timestamp da última atualização do registro.
 * @property-read Pessoa $pessoa A pessoa à qual esta cota pertence.
 */
class CotaEspecial extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    /**
     * Os atributos que podem ser preenchidos em massa.
     */
    protected $fillable = [
        'codigo_pessoa', // Essencial para o formulário
        'valor',         // Essencial para o formulário
    ];

    /**
     * Pega a pessoa à qual esta cota pertence.
     *
     * @return BelongsTo
     */
    public function pessoa()
    {
        return $this->belongsTo(Pessoa::class, 'codigo_pessoa', 'codigo_pessoa');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Representa um vínculo de uma pessoa.
 *
 * @property int $codigo_pessoa A chave estrangeira para a pessoa.
 * @property string $tipo_vinculo O tipo de vínculo (parte da chave primária composta).
 * @property Carbon|null $created_at Timestamp de criação do registro.
 * @property Carbon|null $updated_at Timestamp da última atualização do registro.
 *
 * @property-read Pessoa $pessoa A pessoa à qual este vínculo pertence.
 */
class Vinculo extends Model
{
    use HasFactory;

    /**
     * Pega a pessoa à qual este vínculo pertence.
     *
     * @return BelongsTo
     */
    public function pessoa()
    {
        return $this->belongsTo(Pessoa::class, 'codigo_pessoa', 'codigo_pessoa');
    }
}
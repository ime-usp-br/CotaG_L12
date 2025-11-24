<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Representa um vínculo de uma pessoa.
 *
 * @property int $codigo_pessoa A chave estrangeira para a pessoa.
 * @property string $tipo_vinculo O tipo de vínculo (parte da chave primária composta).
 * @property Carbon|null $created_at Timestamp de criação do registro.
 * @property Carbon|null $updated_at Timestamp da última atualização do registro.
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

    public $timestamps = false;

    protected $table = 'vinculos';

    /**
     * 1. Definimos uma $primaryKey (qualquer uma das chaves) para
     * impedir o Eloquent de usar 'id' por padrão.
     * 2. Mantemos $incrementing como false.
     */
    protected $primaryKey = 'codigo_pessoa';

    public $incrementing = false;

    /**
     * Os atributos que podem ser preenchidos em massa.
     */
    protected $fillable = [
        'codigo_pessoa',
        'tipo_vinculo',
    ];
}

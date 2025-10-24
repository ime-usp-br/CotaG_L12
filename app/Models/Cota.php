<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Representa uma cota no sistema.
 *
 * @property int $id A chave primária da cota.
 * @property int $valor O valor associado à cota.
 * @property string $tipo_vinculo O tipo de vínculo ao qual a cota se aplica.
 * @property Carbon|null $created_at Timestamp de criação do registro.
 * @property Carbon|null $updated_at Timestamp da última atualização do registro.
 */
class Cota extends Model
{
    use HasFactory;

    /**
     * Os atributos que podem ser preenchidos em massa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tipo_vinculo',
        'valor',
    ];
}
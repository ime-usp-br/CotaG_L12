<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Representa uma pessoa no sistema.
 *
 * @property int $codigo_pessoa A chave primária da pessoa.
 * @property string $nome_pessoa O nome completo da pessoa.
 * @property Carbon|null $created_at Timestamp de criação do registro.
 * @property Carbon|null $updated_at Timestamp da última atualização do registro.
 *
 * @property-read Collection|Lancamento[] $lancamentos A coleção de lançamentos associados.
 * @property-read Collection|Vinculo[] $vinculos A coleção de vínculos associados.
 * @property-read CotaEspecial|null $cotaEspecial A cota especial associada (pode não existir).
 */
class Pessoa extends Model
{
    /**
     * A chave primária da tabela.
     * @var string
     */
    protected $primaryKey = 'codigo_pessoa';

    // Seus relacionamentos ...
    public function lancamentos()
    {
        return $this->hasMany(Lancamento::class, 'codigo_pessoa', 'codigo_pessoa');
    }

    public function vinculos()
    {
        return $this->hasMany(Vinculo::class, 'codigo_pessoa', 'codigo_pessoa');
    }

    public function cotaEspecial()
    {
        return $this->hasOne(CotaEspecial::class, 'codigo_pessoa', 'codigo_pessoa');
    }
}
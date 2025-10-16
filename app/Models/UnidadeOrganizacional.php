<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Representa uma Unidade Organizacional (OU) no sistema.
 *
 * @property int $id A chave primária da unidade.
 * @property string $nome O nome completo da unidade.
 * @property string $sigla A sigla da unidade.
 * @property Carbon|null $created_at Timestamp de criação do registro.
 * @property Carbon|null $updated_at Timestamp da última atualização do registro.
 *
 * @property-read Collection|Grupo[] $grupos A coleção de grupos pertencentes a esta unidade.
 */
class UnidadeOrganizacional extends Model
{
    /**
     * Pega todos os grupos pertencentes a esta unidade.
     *
     * @return HasMany
     */
    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class);
    }
}
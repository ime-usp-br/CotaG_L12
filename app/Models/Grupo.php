<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Representa um grupo de usuários, pertencente a uma Unidade Organizacional.
 *
 * @property int $id A chave primária do grupo.
 * @property string $nome O nome do grupo.
 * @property int $unidade_organizacional_id A chave estrangeira para a Unidade Organizacional.
 * @property Carbon|null $created_at Timestamp de criação do registro.
 * @property Carbon|null $updated_at Timestamp da última atualização do registro.
 *
 * @property-read UnidadeOrganizacional $unidadeOrganizacional A unidade organizacional à qual este grupo pertence.
 * @property-read Collection|User[] $usuarios A coleção de usuários que pertencem a este grupo.
 */
class Grupo extends Model
{
    /**
     * Pega a unidade organizacional à qual este grupo pertence.
     *
     * @return BelongsTo
     */
    public function unidadeOrganizacional(): BelongsTo
    {
        return $this->belongsTo(UnidadeOrganizacional::class);
    }

    /**
     * Os usuários que pertencem a este grupo.
     *
     * @return BelongsToMany
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'grupo_usuario');
    }
}
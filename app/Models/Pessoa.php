<?php

namespace App\Models;

use App\Services\CotaService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
    use HasFactory;

    /**
     * A chave primária da tabela.
     * @var string
     */
    protected $primaryKey = 'codigo_pessoa';

    /**
     * Indica se os IDs do modelo são auto-incrementáveis.
     *
     * Por padrão, o Eloquent assume que a chave primária é um inteiro que se
     * auto-incrementa. Definir esta propriedade como `false` informa ao Eloquent
     * que a chave primária (`codigo_pessoa`) não é auto-incrementável e seu
     * valor será atribuído manualmente na criação de um novo registro.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Os atributos que podem ser preenchidos em massa.
     * (ESTA É A CORREÇÃO)
     * @var array<int, string>
     */
    protected $fillable = [
        'codigo_pessoa',
        'nome_pessoa',
    ];

    /**
     * Pega todos os lançamentos financeiros associados a esta pessoa.
     *
     * O relacionamento é definido explicitamente com as chaves 'codigo_pessoa'
     * para corresponder à estrutura não-convencional do banco de dados.
     *
     * @return HasMany
     */
    public function lancamentos()
    {
        return $this->hasMany(Lancamento::class, 'codigo_pessoa', 'codigo_pessoa');
    }

    /**
     * Pega todos os vínculos (ex: Aluno, Servidor) associados a esta pessoa.
     *
     * @return HasMany
     */
    public function vinculos()
    {
        return $this->hasMany(Vinculo::class, 'codigo_pessoa', 'codigo_pessoa');
    }

    /**
     * Pega a cota especial associada a esta pessoa, se existir.
     *
     * Este é um relacionamento um-para-um, significando que uma pessoa
     * pode ter no máximo uma cota especial.
     *
     * @return HasOne
     */
    public function cotaEspecial()
    {
        return $this->hasOne(CotaEspecial::class, 'codigo_pessoa', 'codigo_pessoa');
    }

    /**
     * Accessor para o atributo "saldo".
     *
     * Este método permite acessar o saldo de cotas de impressão de uma pessoa
     * como se fosse um atributo do modelo (ex: $pessoa->saldo).
     *
     * @param CotaService $cotaService O serviço que calcula o saldo.
     * @return float O saldo de cotas de impressão.
     */
    public function getSaldoAttribute(CotaService $cotaService): float
    {
        return $cotaService->calcularSaldo($this);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pessoa extends Model
{
    //

    protected $primaryKey = 'codigo_pessoa';

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

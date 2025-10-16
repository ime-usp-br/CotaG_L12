<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vinculo extends Model
{
    //
    public function pessoa()
    {
        return $this->belongsTo(Pessoa::class, 'codigo_pessoa', 'codigo_pessoa');
    }
}

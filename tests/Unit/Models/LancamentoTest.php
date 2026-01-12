<?php

namespace Tests\Unit\Models;

use App\Models\Lancamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LancamentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_mes_atual_lancamento()
    {
        $lancamentoAtual = Lancamento::factory()->create(['data' => now()]);
        $lancamentoPassado = Lancamento::factory()->create(['data' => now()->subMonth()]);
        $lancamentoFuturo = Lancamento::factory()->create(['data' => now()->addMonth()]);

        $lancamentos = Lancamento::mesAtual()->get();

        $this->assertTrue($lancamentos->contains($lancamentoAtual));
        $this->assertFalse($lancamentos->contains($lancamentoPassado));
        $this->assertFalse($lancamentos->contains($lancamentoFuturo));
    }
}

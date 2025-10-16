<?php

namespace Tests\Feature;

use App\Models\CotaEspecial;
use App\Models\Lancamento;
use App\Models\Pessoa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PessoaRelationshipTest extends TestCase
{
    /**
     * Usa o trait RefreshDatabase para recriar o banco de dados antes de cada teste.
     */
    use RefreshDatabase;

    /**
     * Testa se uma pessoa pode ter muitos lançamentos.
     * O nome do método deve começar com "test".
     * @return void
     */
    public function test_uma_pessoa_pode_ter_muitos_lancamentos(): void
    {
        // Arrange: Preparamos o cenário
        $pessoa = Pessoa::factory()->create();
        Lancamento::factory()->count(3)->create([
            'codigo_pessoa' => $pessoa->codigo_pessoa
        ]);

        // Act: Executamos a ação que queremos testar
        $lancamentosDaPessoa = $pessoa->lancamentos;

        // Assert: Verificamos se o resultado é o esperado usando os métodos do PHPUnit
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $lancamentosDaPessoa);
        $this->assertCount(3, $lancamentosDaPessoa);
        $this->assertInstanceOf(Lancamento::class, $lancamentosDaPessoa->first());
    }

    /**
     * Testa se um lançamento pertence a uma pessoa.
     * @return void
     */
    public function test_um_lancamento_pertence_a_uma_pessoa(): void
    {
        // Arrange
        $pessoa = Pessoa::factory()->create();
        $lancamento = Lancamento::factory()->create([
            'codigo_pessoa' => $pessoa->codigo_pessoa
        ]);

        // Act
        $pessoaDoLancamento = $lancamento->pessoa;

        // Assert
        $this->assertInstanceOf(Pessoa::class, $pessoaDoLancamento);
        $this->assertEquals($pessoa->codigo_pessoa, $pessoaDoLancamento->codigo_pessoa);
    }

    /**
     * Testa se uma pessoa pode ter uma cota especial.
     * @return void
     */
    public function test_uma_pessoa_pode_ter_uma_cota_especial(): void
    {
        // Arrange
        $pessoa = Pessoa::factory()->create();
        CotaEspecial::factory()->create([
            'codigo_pessoa' => $pessoa->codigo_pessoa
        ]);

        // Act
        $cotaDaPessoa = $pessoa->cotaEspecial;

        // Assert
        $this->assertInstanceOf(CotaEspecial::class, $cotaDaPessoa);
    }

    /**
     * Testa se uma cota especial pertence a uma pessoa.
     * @return void
     */
    public function test_uma_cota_especial_pertence_a_uma_pessoa(): void
    {
        // Arrange
        $pessoa = Pessoa::factory()->create();
        $cota = CotaEspecial::factory()->create([
            'codigo_pessoa' => $pessoa->codigo_pessoa
        ]);

        // Act
        $pessoaDaCota = $cota->pessoa;

        // Assert
        $this->assertInstanceOf(Pessoa::class, $pessoaDaCota);
        $this->assertEquals($pessoa->codigo_pessoa, $pessoaDaCota->codigo_pessoa);
    }
}
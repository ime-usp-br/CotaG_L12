<?php

namespace Tests\Feature;

use App\Models\CotaEspecial;
use App\Models\Lancamento;
use App\Models\Pessoa;
use App\Models\Vinculo;
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

    /**
     * Testa se uma pessoa pode ter muitos vínculos.
     * @return void
     */
    public function test_uma_pessoa_pode_ter_muitos_vinculos(): void
    {
        // Arrange
        $pessoa = Pessoa::factory()->create();
        // Criamos 2 vínculos com tipo_vinculo diferentes para a mesma pessoa
        Vinculo::factory()->create([
            'codigo_pessoa' => $pessoa->codigo_pessoa,
            'tipo_vinculo' => 'ALUNO'
        ]);
        Vinculo::factory()->create([
            'codigo_pessoa' => $pessoa->codigo_pessoa,
            'tipo_vinculo' => 'SERVIDOR'
        ]);

        // Act
        $vinculosDaPessoa = $pessoa->vinculos;

        // Assert
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $vinculosDaPessoa);
        $this->assertCount(2, $vinculosDaPessoa);
        $this->assertInstanceOf(\App\Models\Vinculo::class, $vinculosDaPessoa->first());
    }

    /**
     * Testa se um vínculo pertence a uma pessoa.
     * @return void
     */
    public function test_um_vinculo_pertence_a_uma_pessoa(): void
    {
        // Arrange
        $pessoa = Pessoa::factory()->create();
        $vinculo = Vinculo::factory()->create([
            'codigo_pessoa' => $pessoa->codigo_pessoa,
        ]);

        // Act
        $pessoaDoVinculo = $vinculo->pessoa;

        // Assert
        $this->assertInstanceOf(\App\Models\Pessoa::class, $pessoaDoVinculo);
        $this->assertEquals($pessoa->codigo_pessoa, $pessoaDoVinculo->codigo_pessoa);
    }
}
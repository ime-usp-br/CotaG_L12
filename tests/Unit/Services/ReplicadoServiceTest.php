<?php

namespace Tests\Unit\Services;

use App\Models\Pessoa;
use App\Models\Vinculo;
use App\Services\ReplicadoService;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ReplicadoServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReplicadoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReplicadoService();
    }

    public function test_deve_retornar_pessoa_se_encontrada_localmente()
    {
        // Cria pessoa local
        $pessoa = Pessoa::factory()->create([
            'codigo_pessoa' => 123456,
            'nome_pessoa' => 'Pessoa Local Teste'
        ]);
        
        // Cria vinculo
        Vinculo::factory()->create([
            'codigo_pessoa' => $pessoa->codigo_pessoa,
            'tipo_vinculo' => 'DOCENTE'
        ]);

        // Garante que não tenta conectar no Replicado (deve achar local)
        DB::shouldReceive('connection')->never();

        // Executa busca
        $resultado = $this->service->buscarPessoaPorCodpes('123456');

        // Asserts
        $this->assertNotNull($resultado);
        $this->assertEquals(123456, $resultado['codpes']);
        $this->assertEquals('Pessoa Local Teste', $resultado['nompes']);
        $this->assertContains('DOCENTE', $resultado['vinculos']);
    }

    public function test_deve_retornar_nulo_se_pessoa_nao_existir()
    {
        // 1. Mock do Connection Object
        $connectionMock = Mockery::mock(Connection::class);
        $connectionMock->shouldReceive('select')
            ->andReturn([]); // Retorna array vazio quando 'select' for chamado

        // 2. Mock do DB Facade para retornar o Connection Mock
        DB::shouldReceive('connection')
            ->with('replicado')
            ->andReturn($connectionMock);

        $resultado = $this->service->buscarPessoaPorCodpes('999999');

        $this->assertNull($resultado);
    }
}

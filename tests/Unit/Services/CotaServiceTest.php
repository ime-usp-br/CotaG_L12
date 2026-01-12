<?php

namespace Tests\Unit\Services;

use App\Models\Cota;
use App\Models\CotaEspecial;
use App\Models\Lancamento;
use App\Models\Pessoa;
use App\Models\Vinculo;
use App\Services\CotaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CotaServiceTest extends TestCase
{
    use RefreshDatabase;

    private CotaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CotaService::class);
    }

    public function test_deve_priorizar_cota_especial()
    {
        $pessoa = Pessoa::factory()->create();

        Vinculo::factory()->create(['codigo_pessoa' => $pessoa->codigo_pessoa, 'tipo_vinculo' => 'DOCENTE']);
        Cota::factory()->create(['tipo_vinculo' => 'DOCENTE', 'valor' => 100]);

        CotaEspecial::factory()->create(['codigo_pessoa' => $pessoa->codigo_pessoa, 'valor' => 500]);

        $saldo = $this->service->calcularSaldo($pessoa);
        $this->assertEquals(500, $saldo);
    }

    public function test_deve_escolher_maior_cota_entre_multiplos_vinculos()
    {
        $pessoa = Pessoa::factory()->create();

        Vinculo::factory()->create(['codigo_pessoa' => $pessoa->codigo_pessoa, 'tipo_vinculo' => 'ALUNO']);
        Cota::factory()->create(['tipo_vinculo' => 'ALUNO', 'valor' => 50]);

        Vinculo::factory()->create(['codigo_pessoa' => $pessoa->codigo_pessoa, 'tipo_vinculo' => 'ESTAGIARIO']);
        Cota::factory()->create(['tipo_vinculo' => 'ESTAGIARIO', 'valor' => 100]);

        $saldo = $this->service->calcularSaldo($pessoa);
        $this->assertEquals(100, $saldo);
    }

    public function test_calculo_de_saldo_com_debitos()
    {
        $pessoa = Pessoa::factory()->create();
        
        Vinculo::factory()->create(['codigo_pessoa' => $pessoa->codigo_pessoa, 'tipo_vinculo' => 'DOCENTE']);
        Cota::factory()->create(['tipo_vinculo' => 'DOCENTE', 'valor' => 200]);

        Lancamento::factory()->create([
            'codigo_pessoa' => $pessoa->codigo_pessoa,
            'valor' => 30,
            'tipo_lancamento' => 1,
            'data' => now(),
        ]);

        $saldo = $this->service->calcularSaldo($pessoa);
        $this->assertEquals(170, $saldo);
    }

    public function test_saldo_reseta_no_novo_mes()
    {
        $pessoa = Pessoa::factory()->create();
        
        Vinculo::factory()->create(['codigo_pessoa' => $pessoa->codigo_pessoa, 'tipo_vinculo' => 'DOCENTE']);
        Cota::factory()->create(['tipo_vinculo' => 'DOCENTE', 'valor' => 200]);

        Lancamento::factory()->create([
            'codigo_pessoa' => $pessoa->codigo_pessoa,
            'valor' => 200,
            'tipo_lancamento' => 1,
            'data' => now()->subMonth(),
        ]);

        $saldo = $this->service->calcularSaldo($pessoa);
        $this->assertEquals(200, $saldo);
    }

    public function test_saldo_para_pessoa_sem_vinculo()
    {
        $pessoa = Pessoa::factory()->create();

        $saldo = $this->service->calcularSaldo($pessoa);
        $this->assertEquals(0, $saldo);
    }
}

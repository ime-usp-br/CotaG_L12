<?php

namespace Tests\Feature\Services;

use App\Models\Cota;
use App\Models\CotaEspecial;
use App\Models\Lancamento;
use App\Models\Pessoa;
use App\Models\Vinculo;
use App\Services\CotaService; // Nosso serviço
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CotaServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @var CotaService */
    protected $cotaService;

    /**
     * Prepara o ambiente de teste pegando uma instância do serviço.
     */
    protected function setUp(): void
    {
        parent::setUp();
        // Pedimos ao Laravel uma instância do nosso serviço
        $this->cotaService = $this->app->make(CotaService::class);
    }

    /**
     * Teste (Critério 3.1): Pessoa com Cota Especial.
     */
    public function test_calcula_saldo_corretamente_com_cota_especial(): void
    {
        // Arrange
        $pessoa = Pessoa::factory()->create();
        CotaEspecial::factory()->create([
            'codigo_pessoa' => $pessoa->codigo_pessoa,
            'valor' => 1000, // Cota Base = 1000
        ]);

        // Lançamento de débito (tipo 1) este mês
        Lancamento::factory()->create([
            'codigo_pessoa' => $pessoa->codigo_pessoa,
            'valor' => 300,
            'tipo_lancamento' => 1,
            'data' => now(),
        ]);

        // Act
        $saldo = $this->cotaService->calcularSaldo($pessoa);

        // Assert (1000 - 300)
        $this->assertEquals(700, $saldo);
    }

    /**
     * Teste (Critério 3.2): Pessoa com Cota Padrão (Vínculos).
     */
    public function test_calcula_saldo_corretamente_com_cota_padrao_de_vinculos(): void
    {
        // Arrange
        $pessoa = Pessoa::factory()->create();

        // Criamos as cotas padrão que existem no sistema
        Cota::factory()->create(['tipo_vinculo' => 'ALUNO', 'valor' => 200]);
        Cota::factory()->create(['tipo_vinculo' => 'SERVIDOR', 'valor' => 500]);

        // Damos um vínculo de ALUNO para a pessoa
        Vinculo::factory()->create([
            'codigo_pessoa' => $pessoa->codigo_pessoa,
            'tipo_vinculo' => 'ALUNO', // Cota Base = 200
        ]);

        // Lançamento de débito (tipo 1) este mês
        Lancamento::factory()->create([
            'codigo_pessoa' => $pessoa->codigo_pessoa,
            'valor' => 50,
            'tipo_lancamento' => 1,
            'data' => now(),
        ]);

        // Act
        $saldo = $this->cotaService->calcularSaldo($pessoa);

        // Assert (200 - 50)
        $this->assertEquals(150, $saldo);
    }

    /**
     * Teste (Critério 3.2): Pessoa com múltiplos vínculos (deve pegar a cota MÁXIMA).
     */
    public function test_pega_a_maior_cota_quando_pessoa_tem_multiplos_vinculos(): void
    {
        // Arrange
        $pessoa = Pessoa::factory()->create();
        Cota::factory()->create(['tipo_vinculo' => 'ALUNO', 'valor' => 200]);
        Cota::factory()->create(['tipo_vinculo' => 'SERVIDOR', 'valor' => 500]);

        // A pessoa tem AMBOS os vínculos
        Vinculo::factory()->create(['codigo_pessoa' => $pessoa->codigo_pessoa, 'tipo_vinculo' => 'ALUNO']);
        Vinculo::factory()->create(['codigo_pessoa' => $pessoa->codigo_pessoa, 'tipo_vinculo' => 'SERVIDOR']);
        // Cota Base deve ser 500 (a máxima)

        Lancamento::factory()->create(['codigo_pessoa' => $pessoa->codigo_pessoa, 'valor' => 100, 'tipo_lancamento' => 1, 'data' => now()]);

        // Act
        $saldo = $this->cotaService->calcularSaldo($pessoa);

        // Assert (500 - 100)
        $this->assertEquals(400, $saldo);
    }

    /**
     * Teste (Critério 3.1 & 3.2): Cota Especial deve ter prioridade sobre a Cota Padrão.
     */
    public function test_cota_especial_tem_prioridade_sobre_cota_padrao(): void
    {
        // Arrange
        $pessoa = Pessoa::factory()->create();

        // Tem cota especial (1000)
        CotaEspecial::factory()->create(['codigo_pessoa' => $pessoa->codigo_pessoa, 'valor' => 1000]);

        // Mas também tem vínculo de servidor (cota 500)
        Cota::factory()->create(['tipo_vinculo' => 'SERVIDOR', 'valor' => 500]);
        Vinculo::factory()->create(['codigo_pessoa' => $pessoa->codigo_pessoa, 'tipo_vinculo' => 'SERVIDOR']);

        Lancamento::factory()->create(['codigo_pessoa' => $pessoa->codigo_pessoa, 'valor' => 100, 'tipo_lancamento' => 1, 'data' => now()]);

        // Act
        $saldo = $this->cotaService->calcularSaldo($pessoa);

        // Assert: O saldo deve ser calculado sobre a cota especial (1000 - 100)
        $this->assertEquals(900, $saldo);
    }

    /**
     * Teste (Critério 3.3): Lançamentos de meses passados não devem ser contados.
     */
    public function test_lancamentos_de_meses_passados_nao_sao_contados(): void
    {
        // Arrange
        $pessoa = Pessoa::factory()->create();
        CotaEspecial::factory()->create(['codigo_pessoa' => $pessoa->codigo_pessoa, 'valor' => 1000]); // Cota = 1000

        // Lançamento DESTE mês
        Lancamento::factory()->create(['codigo_pessoa' => $pessoa->codigo_pessoa, 'valor' => 100, 'tipo_lancamento' => 1, 'data' => now()]);

        // Lançamento do MÊS PASSADO
        Lancamento::factory()->create(['codigo_pessoa' => $pessoa->codigo_pessoa, 'valor' => 500, 'tipo_lancamento' => 1, 'data' => now()->subMonth()]);

        // Act
        $saldo = $this->cotaService->calcularSaldo($pessoa);

        // Assert: Apenas o lançamento de 100 deve ser subtraído (1000 - 100)
        $this->assertEquals(900, $saldo);
    }

    /**
     * Teste (Critério 3.3): Apenas lançamentos de DÉBITO (tipo 1) devem ser contados.
     */
    public function test_lancamentos_de_credito_tipo_0_nao_sao_contados(): void
    {
        // Arrange
        $pessoa = Pessoa::factory()->create();
        CotaEspecial::factory()->create(['codigo_pessoa' => $pessoa->codigo_pessoa, 'valor' => 1000]); // Cota = 1000

        // Lançamento de DÉBITO (tipo 1)
        Lancamento::factory()->create(['codigo_pessoa' => $pessoa->codigo_pessoa, 'valor' => 100, 'tipo_lancamento' => 1, 'data' => now()]);

        // Lançamento de CRÉDITO (tipo 0)
        Lancamento::factory()->create(['codigo_pessoa' => $pessoa->codigo_pessoa, 'valor' => 500, 'tipo_lancamento' => 0, 'data' => now()]);

        // Act
        $saldo = $this->cotaService->calcularSaldo($pessoa);

        // Assert: Apenas o débito de 100 deve ser subtraído (1000 - 100)
        $this->assertEquals(900, $saldo);
    }

    /**
     * Teste (Critério 5): Pessoa sem nenhuma cota deve ter saldo 0 (ou negativo se tiver lançamentos).
     */
    public function test_pessoa_sem_cota_especial_ou_vinculo_tem_cota_base_zero(): void
    {
        // Arrange
        $pessoa = Pessoa::factory()->create();
        // Esta pessoa não tem CotaEspecial nem Vínculo

        // Mas ela tem um lançamento de débito (situação estranha, mas o serviço deve lidar)
        Lancamento::factory()->create(['codigo_pessoa' => $pessoa->codigo_pessoa, 'valor' => 50, 'tipo_lancamento' => 1, 'data' => now()]);

        // Act
        $saldo = $this->cotaService->calcularSaldo($pessoa);

        // Assert: A cota base é 0, então o saldo é negativo (0 - 50)
        $this->assertEquals(-50, $saldo);
    }
}

<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Lancamento\ManageLancamentos;
use App\Models\Cota;
use App\Models\Pessoa;
use App\Models\User;
use App\Models\Vinculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManageLancamentosTest extends TestCase
{
    use RefreshDatabase;

    private User $operador;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup permissions
        $role = Role::create(['name' => 'OPR']);
        $permission = Permission::create(['name' => 'operar-sistema']); // Adjust permission name if key is different
        $role->givePermissionTo($permission); // Assuming OPR has this perms

        $this->operador = User::factory()->create();
        $this->operador->assignRole('OPR');
        $this->operador->givePermissionTo('operar-sistema');
    }

    public function test_operador_pode_acessar_tela()
    {
        Livewire::actingAs($this->operador)
            ->test(ManageLancamentos::class)
            ->assertStatus(200);
    }

    public function test_busca_de_pessoa_funciona()
    {
        Pessoa::factory()->create(['nome_pessoa' => 'Joao da Silva', 'codigo_pessoa' => 123]);

        Livewire::actingAs($this->operador)
            ->test(ManageLancamentos::class)
            ->set('termoBusca', 'Joao') 
            ->call('buscarPessoa') 
            ->assertSee('Joao da Silva');
    }

    public function test_selecao_de_pessoa_carrega_perfil_e_saldo()
    {
        $pessoa = Pessoa::factory()->create(['nome_pessoa' => 'Maria', 'codigo_pessoa' => 456]);
        Vinculo::factory()->create(['codigo_pessoa' => 456, 'tipo_vinculo' => 'DOCENTE']);
        Cota::factory()->create(['tipo_vinculo' => 'DOCENTE', 'valor' => 100]); // Saldo 100

        Livewire::actingAs($this->operador)
            ->test(ManageLancamentos::class)
            ->call('selecionarPessoa', $pessoa->codigo_pessoa) 
            ->assertSee('Maria')
            ->assertSee('100'); // Saldo
    }

    public function test_realizar_lancamento_debito()
    {
        $pessoa = Pessoa::factory()->create(['codigo_pessoa' => 789]);
        Vinculo::factory()->create(['codigo_pessoa' => 789, 'tipo_vinculo' => 'DOCENTE']);
        Cota::factory()->create(['tipo_vinculo' => 'DOCENTE', 'valor' => 200]);

        Livewire::actingAs($this->operador)
            ->test(ManageLancamentos::class)
            ->call('selecionarPessoa', $pessoa->codigo_pessoa)
            ->call('abrirModalLancamento', 1) // 1 = Débito
            ->set('valorLancamento', 50)
            ->call('salvarLancamento')
            ->assertDispatched('alert', 'Lançamento realizado com sucesso!');

        $this->assertDatabaseHas('lancamentos', [
            'codigo_pessoa' => 789,
            'valor' => 50,
            'tipo_lancamento' => 1
        ]);
    }

    public function test_validacao_de_formulario()
    {
        $pessoa = Pessoa::factory()->create();

        Livewire::actingAs($this->operador)
            ->test(ManageLancamentos::class)
            ->call('selecionarPessoa', $pessoa->codigo_pessoa)
             ->call('abrirModalLancamento', 1)
            ->set('valorLancamento', -10) // Negativo
            ->call('salvarLancamento')
            ->assertHasErrors(['valorLancamento']);
    }

    public function test_historico_atualiza_apos_lancamento()
    {
        $pessoa = Pessoa::factory()->create();
        
        Livewire::actingAs($this->operador)
            ->test(ManageLancamentos::class)
            ->call('selecionarPessoa', $pessoa->codigo_pessoa)
            ->call('abrirModalLancamento', 1)
            ->set('valorLancamento', 10)
            ->call('salvarLancamento');
            
        $this->assertDatabaseHas('lancamentos', ['valor' => 10]);
    }
}

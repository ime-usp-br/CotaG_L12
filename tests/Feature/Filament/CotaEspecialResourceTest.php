<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\CotaEspecials\Pages\CreateCotaEspecial;
use App\Models\Pessoa;
use App\Models\User;
use App\Services\ReplicadoService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CotaEspecialResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Admin role
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('Admin');
        $this->actingAs($user);

        // Set the current Filament panel to 'admin'
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_can_create_cota_especial_for_existing_person()
    {
        $pessoa = Pessoa::factory()->create();

        Livewire::test(CreateCotaEspecial::class)
            ->fillForm([
                'codigo_pessoa' => (string) $pessoa->codigo_pessoa,
                'valor' => 100,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('cota_especials', [
            'codigo_pessoa' => $pessoa->codigo_pessoa,
            'valor' => 100,
        ]);
    }

    public function test_can_create_cota_especial_importing_from_replicado()
    {
        $codpes = '123456';
        $nompes = 'Replicado User';
        $vinculo = 'ALUNOGR';

        $this->mock(ReplicadoService::class, function ($mock) use ($codpes, $nompes, $vinculo) {
            $mock->shouldReceive('buscarPessoaPorCodpes')
                ->with($codpes)
                ->once()
                ->andReturn([
                    'codpes' => (int) $codpes,
                    'nompes' => $nompes,
                    'vinculos' => [$vinculo],
                ]);
        });

        Livewire::test(CreateCotaEspecial::class)
            ->fillForm([
                'codigo_pessoa' => $codpes,
                'valor' => 500,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('pessoas', [
            'codigo_pessoa' => $codpes,
            'nome_pessoa' => $nompes,
        ]);

        $this->assertDatabaseHas('vinculos', [
            'codigo_pessoa' => $codpes,
            'tipo_vinculo' => $vinculo,
        ]);

        $this->assertDatabaseHas('cota_especials', [
            'codigo_pessoa' => $codpes,
            'valor' => 500,
        ]);
    }

    public function test_validation_fails_if_user_not_found()
    {
        $codpes = '999999';

        $this->mock(ReplicadoService::class, function ($mock) use ($codpes) {
            $mock->shouldReceive('buscarPessoaPorCodpes')
                ->with($codpes)
                ->once()
                ->andReturnNull();
        });

        Livewire::test(CreateCotaEspecial::class)
            ->fillForm([
                'codigo_pessoa' => $codpes,
                'valor' => 100,
            ])
            ->call('create')
            ->assertHasFormErrors(['codigo_pessoa']);

        $this->assertDatabaseMissing('cota_especials', ['codigo_pessoa' => $codpes]);
    }
}

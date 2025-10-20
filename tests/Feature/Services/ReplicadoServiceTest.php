<?php

namespace Tests\Feature\Services;

use App\Models\User;
use App\Services\ReplicadoService; // Importa o serviço que vamos testar
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReplicadoServiceTest extends TestCase
{
    // Este trait mágico limpa e recria seu banco de dados
    // em memória antes de cada teste.
    use RefreshDatabase;

    /**
     * Testa se o serviço consegue encontrar um usuário pelo seu 'codigo_pessoa'.
     * @return void
     */
    public function test_ele_encontra_um_usuario_pelo_codpes(): void
    {
        // Arrange (Preparação): Criamos um usuário com um 'codigo_pessoa' específico.
        $user = User::factory()->create([
            'codigo_pessoa' => '12345678',
            'name' => 'Usuário Teste Codpes',
            'email' => 'codigo_pessoa@teste.com'
        ]);

        // Act (Ação): Pedimos ao Service Container do Laravel para nos dar
        // uma instância do nosso serviço e então o executamos.
        $service = $this->app->make(ReplicadoService::class);
        $resultado = $service->buscarPessoa('12345678');

        // Assert (Verificação): Verificamos se o resultado é o esperado.
        $this->assertNotNull($resultado); // O resultado não pode ser nulo
        $this->assertInstanceOf(User::class, $resultado); // Deve ser um objeto User
        $this->assertEquals($user->id, $resultado->id); // Deve ser o usuário EXATO que criamos
        $this->assertEquals('12345678', $resultado->codigo_pessoa);
    }

    /**
     * Testa se o serviço consegue encontrar um usuário pelo seu 'email'.
     * @return void
     */
    public function test_ele_encontra_um_usuario_pelo_email(): void
    {
        // Arrange
        $user = User::factory()->create([
            'codigo_pessoa' => '999999',
            'name' => 'Usuário Teste Email',
            'email' => 'email.exato@teste.com'
        ]);

        // Act
        $service = $this->app->make(ReplicadoService::class);
        $resultado = $service->buscarPessoa('email.exato@teste.com');

        // Assert
        $this->assertNotNull($resultado);
        $this->assertEquals($user->id, $resultado->id);
    }

    /**
     * Testa se o serviço consegue encontrar um usuário por uma parte do seu 'name'.
     * @return void
     */
    public function test_ele_encontra_um_usuario_pelo_nome_parcial(): void
    {
        // Arrange
        $user = User::factory()->create([
            'codigo_pessoa' => '777777',
            'name' => 'Maria Joaquina de Amaral',
            'email' => 'maria@teste.com'
        ]);

        // Act: Buscamos por um pedaço do nome
        $service = $this->app->make(ReplicadoService::class);
        $resultado = $service->buscarPessoa('Joaquina');

        // Assert
        $this->assertNotNull($resultado);
        $this->assertEquals($user->id, $resultado->id);
        $this->assertEquals('Maria Joaquina de Amaral', $resultado->name);
    }

    /**
     * Testa se o serviço retorna 'null' quando o critério não corresponde a ninguém.
     * @return void
     */
    public function test_ele_retorna_null_quando_nao_encontra_ninguem(): void
    {
        // Arrange: Criamos um usuário qualquer só para o banco não estar vazio.
        User::factory()->create(['name' => 'Pessoa Aleatória']);

        // Act: Buscamos por algo que definitivamente não existe.
        $service = $this->app->make(ReplicadoService::class);
        $resultado = $service->buscarPessoa('CRITERIO_QUE_NAO_EXISTE_999');

        // Assert
        $this->assertNull($resultado); // A resposta DEVE ser nula.
    }
}
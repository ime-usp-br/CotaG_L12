<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $operador;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear Permission Cache
        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Create Role
        Role::create(['name' => 'ADM']);
        Role::create(['name' => 'OPR']);
        Role::create(['name' => 'Admin']); // Legacy/Fallback

        $this->admin = User::factory()->create();
        $this->admin->assignRole('ADM');

        $this->operador = User::factory()->create();
        $this->operador->assignRole('OPR');
    }

    public function test_operador_nao_pode_acessar_admin()
    {
        $this->actingAs($this->operador)
            ->get('/admin')
            ->assertStatus(403);
    }

    public function test_admin_pode_acessar_painel()
    {
        $this->actingAs($this->admin)
            ->get('/admin')
            ->assertStatus(200);
    }
}

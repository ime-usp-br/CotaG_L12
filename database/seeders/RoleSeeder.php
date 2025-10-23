<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 *Seeder para popular a tabela de roles com os papéis padrão da aplicação.
 */
class RoleSeeder extends Seeder
{
    /**
     *Executa o seeder para o banco de dados.
     *
     *Cria os papéis (roles) padrão 'usp_user' e 'external_user' para o guard 'web'.
     *
     *Limpa o cache de permissões antes de criar os papéis para garantir consistência.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles for local authentication
        Role::firstOrCreate(['name' => 'usp_user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'external_user', 'guard_name' => 'web']);

        // Admin role for Filament panel access
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Operador', 'guard_name' => 'web']);
    }
}

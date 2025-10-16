<?php

namespace Database\Factories;

use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lancamento>
 */
class LancamentoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'data' => now(),
            'tipo_lancamento' => $this->faker->boolean, // 0 ou 1
            'valor' => $this->faker->numberBetween(100, 1000),
            // Isso garante que o lançamento sempre terá uma pessoa e um usuário válidos.
            'codigo_pessoa' => Pessoa::factory(),
            'usuario_id' => User::factory(),
        ];
    }
}

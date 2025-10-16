<?php

namespace Database\Factories;

use App\Models\Pessoa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vinculo>
 */
class VinculoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Garante que o vínculo sempre terá uma pessoa válida associada.
            'codigo_pessoa' => Pessoa::factory(),
            // Gera uma palavra aleatória para o tipo de vínculo.
            'tipo_vinculo' => $this->faker->word(),
        ];
    }
}

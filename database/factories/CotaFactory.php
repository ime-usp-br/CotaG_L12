<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CotaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo_vinculo' => $this->faker->word(),
            'valor' => $this->faker->numberBetween(100, 500), // Valor padrão
        ];
    }
}

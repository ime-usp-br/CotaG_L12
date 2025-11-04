<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Cota; // Importe o Model

class CotaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Use updateOrCreate para definir as regras de negócio
        // O CotaService vai ler estes valores.
        
        // Simulação 1: Cota de Aluno de Graduação (do seu NUSP de teste)
        Cota::updateOrCreate(
            ['tipo_vinculo' => 'ALUNOGR'], // Chave
            ['valor' => 500]                // Valor da Cota
        );

        // Simulação 2: Estagiário (o outro vínculo do seu NUSP)
        Cota::updateOrCreate(
            ['tipo_vinculo' => 'ESTAGIARIORH'],
            ['valor' => 100] // Cota menor, CotaService pegará a maior (500)
        );

        // Simulação 3: Docente
        Cota::updateOrCreate(
            ['tipo_vinculo' => 'DOCENTE'],
            ['valor' => 1000]
        );
        
        // Simulação 4: Aluno de Pós-Graduação
        Cota::updateOrCreate(
            ['tipo_vinculo' => 'ALUNOPOS'],
            ['valor' => 750]
        );
    }
}

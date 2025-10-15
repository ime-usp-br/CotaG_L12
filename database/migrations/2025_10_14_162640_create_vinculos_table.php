<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vinculos', function (Blueprint $table) {
            $table->integer('codigo_pessoa');
            $table->foreign('codigo_pessoa')
                  ->references('codigo_pessoa')
                  ->on('pessoas')
                  ->onDelete('cascade');
            $table->string('tipo_vinculo');
            $table->primary(['codigo_pessoa', 'tipo_vinculo']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vinculos');
    }
};

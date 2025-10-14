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
        Schema::create('lancamento', function (Blueprint $table) {
            $table->id();
            $table->dateTime('data');
            $table->integer('tipoLancamento'); // (0 = Crédito e 1 = Débito)
            $table->integer('valor');
            $table->integer('pessoa_codpes');
            $table->foreign('pessoa_codpes')
                  ->references('codpes')
                  ->on('pessoa')
                  ->onDelete('cascade');
            $table->integer('usuario_id');
            $table->foreign('usuario_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');  
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lancamento');
    }
};

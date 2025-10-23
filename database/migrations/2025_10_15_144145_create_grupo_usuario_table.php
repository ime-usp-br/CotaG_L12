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
        Schema::create('grupo_usuario', function (Blueprint $table) {
            $table->unsignedBigInteger('usuario_id');
            $table->foreign('usuario_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            $table->unsignedBigInteger('grupo_id');
            $table->foreign('grupo_id')
                  ->references('id')
                  ->on('grupos')
                  ->onDelete('cascade');
            $table->primary(['usuario_id','grupo_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grupo_usuario');
    }
};

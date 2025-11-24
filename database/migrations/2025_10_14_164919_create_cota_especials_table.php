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
        Schema::create('cota_especials', function (Blueprint $table) {
            $table->id();
            $table->integer('codigo_pessoa');
            $table->foreign('codigo_pessoa')
                ->references('codigo_pessoa')
                ->on('pessoas')
                ->onDelete('cascade');
            $table->integer('valor');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cota_especials');
    }
};

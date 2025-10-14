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
        Schema::create('cota_especial', function (Blueprint $table) {
            $table->id();
            $table->integer('codpes');
            $table->foreign('codpes')
                  ->references('codpes')
                  ->on('pessoa')
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
        Schema::dropIfExists('cota_especial');
    }
};

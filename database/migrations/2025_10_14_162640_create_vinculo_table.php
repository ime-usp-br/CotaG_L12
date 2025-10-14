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
        Schema::create('vinculo', function (Blueprint $table) {
            $table->integer('codpes');
            $table->foreign('codpes')
                  ->references('codpes')
                  ->on('pessoa')
                  ->onDelete('cascade');
            $table->string('vinculo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vinculo');
    }
};

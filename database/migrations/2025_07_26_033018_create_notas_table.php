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
        Schema::create('notas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('grupo_id');
            $table->string('titulo');
            $table->longText('contenido');
            $table->uuid('creado_por');
            $table->uuid('ultimo_editor')->nullable();
            $table->datetime('ultima_edicion')->nullable();
            $table->integer('version')->default(1);
            $table->boolean('es_privada')->default(false);
            $table->string('color')->default('#FFFFFF');
            $table->json('etiquetas')->nullable(); // Para categorizar notas
            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('grupos')->onDelete('cascade');
            $table->foreign('creado_por')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('ultimo_editor')->references('id')->on('users')->onDelete('set null');
            $table->index(['grupo_id', 'created_at']);
            $table->index(['grupo_id', 'es_privada']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notas');
    }
};

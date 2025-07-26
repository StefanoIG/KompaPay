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
        Schema::create('tareas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tablero_id');
            $table->uuid('grupo_id');
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->enum('prioridad', ['baja', 'media', 'alta', 'urgente'])->default('media');
            $table->enum('estado', ['pendiente', 'en_progreso', 'completada', 'cancelada'])->default('pendiente');
            $table->integer('orden')->default(0);
            $table->uuid('asignado_a')->nullable();
            $table->uuid('creado_por');
            $table->datetime('fecha_vencimiento')->nullable();
            $table->datetime('completada_en')->nullable();
            $table->timestamps();

            $table->foreign('tablero_id')->references('id')->on('tableros')->onDelete('cascade');
            $table->foreign('grupo_id')->references('id')->on('grupos')->onDelete('cascade');
            $table->foreign('asignado_a')->references('id')->on('users')->onDelete('set null');
            $table->foreign('creado_por')->references('id')->on('users')->onDelete('cascade');
            $table->index(['tablero_id', 'orden']);
            $table->index(['grupo_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tareas');
    }
};

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
        Schema::create('sync_conflictos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('gasto_id');
            $table->string('tipo_conflicto'); // diferencias_datos, concurrencia, conflicto_pago
            $table->uuid('creado_por');
            $table->json('datos_servidor');
            $table->json('datos_cliente');
            $table->enum('estado', ['pendiente', 'resuelto', 'auto_resuelto', 'error'])->default('pendiente');
            $table->uuid('resuelto_por')->nullable();
            $table->timestamp('fecha_creacion');
            $table->timestamp('fecha_resolucion')->nullable();
            $table->json('datos_resolucion')->nullable();
            $table->timestamps();

            $table->foreign('gasto_id')->references('id')->on('gastos')->onDelete('cascade');
            $table->foreign('creado_por')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('resuelto_por')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_conflictos');
    }
};
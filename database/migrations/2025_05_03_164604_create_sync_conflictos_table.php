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
            $table->json('version_a');
            $table->json('version_b');
            $table->uuid('creado_por');
            $table->boolean('resuelto')->default(false);
            $table->boolean('aprobado_por_creador')->default(true);
            $table->boolean('aprobado_por_otro')->default(false);
            $table->timestamp('fecha_conflicto');
            $table->timestamp('resuelto_el')->nullable();
            $table->timestamps();

            $table->foreign('gasto_id')->references('id')->on('gastos')->onDelete('cascade');
            $table->foreign('creado_por')->references('id')->on('users')->onDelete('cascade');
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
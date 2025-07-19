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
        Schema::create('gastos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('grupo_id');
            $table->string('descripcion');
            $table->decimal('monto', 10, 2); // Cambié de monto_total a monto
            $table->enum('tipo_division', ['equitativa', 'porcentaje', 'personalizada'])->default('equitativa');
            $table->uuid('pagado_por');
            $table->uuid('modificado_por')->nullable();
            $table->string('id_publico')->unique();
            $table->text('nota')->nullable();
            $table->timestamp('fecha_creacion');
            $table->timestamp('ultima_modificacion');
            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('grupos')->onDelete('cascade');
            $table->foreign('pagado_por')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('modificado_por')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gastos');
    }
};
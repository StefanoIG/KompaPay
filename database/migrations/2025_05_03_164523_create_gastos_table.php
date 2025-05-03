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
            $table->decimal('monto_total', 10, 2);
            $table->uuid('pagado_por');
            $table->enum('estado_pago', ['pendiente', 'pagado'])->default('pendiente');
            $table->timestamp('ultima_modificacion');
            $table->uuid('modificado_por')->nullable();
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
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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('gasto_id');
            $table->string('accion');
            $table->json('detalle');
            $table->uuid('hecho_por');
            $table->timestamp('timestamp');
            $table->timestamps();

            $table->foreign('gasto_id')->references('id')->on('gastos')->onDelete('cascade');
            $table->foreign('hecho_por')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
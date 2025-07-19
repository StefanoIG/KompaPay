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
        Schema::create('gasto_user', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('gasto_id');
            $table->uuid('user_id');
            $table->decimal('monto_proporcional', 10, 2);
            $table->boolean('pagado')->default(false);
            $table->timestamp('fecha_pago')->nullable();
            $table->timestamps();

            $table->foreign('gasto_id')->references('id')->on('gastos')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->unique(['gasto_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gasto_user');
    }
};
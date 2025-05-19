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
Schema::create('grupo_user', function (Blueprint $table) {
// $table->uuid('id')->primary(); // Elimina esta línea

        $table->uuid('grupo_id');
        $table->uuid('user_id');
        $table->timestamps();

        $table->foreign('grupo_id')->references('id')->on('grupos')->onDelete('cascade');
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

        // Añade una clave primaria compuesta
        $table->primary(['grupo_id', 'user_id']); 
    });
}

/**
 * Reverse the migrations.
 */
public function down(): void
{
    Schema::dropIfExists('grupo_user');
}
};
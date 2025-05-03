<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UsuarioController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Rutas públicas (no requieren autenticación)
Route::post('/register', [UsuarioController::class, 'register']);
Route::post('/login', [UsuarioController::class, 'login']);
Route::post('/users/find', [UsuarioController::class, 'findByPublicId']);

// Rutas protegidas (requieren autenticación)
Route::middleware('auth:sanctum')->group(function () {
    // Rutas de usuario
    Route::get('/user/me', [UsuarioController::class, 'me']);
    Route::put('/user/update', [UsuarioController::class, 'update']);
    Route::post('/user/logout', [UsuarioController::class, 'logout']);
    
    // Rutas de sincronización
    Route::get('/user/sync', [UsuarioController::class, 'syncData']);
    Route::post('/user/sync/update', [UsuarioController::class, 'updateSyncTime']);
    
    // Rutas para obtener datos relacionados UsuUserControlleraUserControllerrial usuario
    Route::get('/user/groups', [UsuarioController::class, 'myGroups']);
    Route::get('/user/expenses', [UsuarioController::class, 'myExpenses']);
    Route::get('/user/conflicts', [UsuarioController::class, 'myConflicts']);
});
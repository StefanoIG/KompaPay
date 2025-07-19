<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\GastoController;
use App\Http\Controllers\SyncConflictoController;
use App\Http\Controllers\AuditLogController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Rutas públicas (no requieren autenticación)
Route::post('/register', [UsuarioController::class, 'register']);
Route::post('/login', [UsuarioController::class, 'login']);
Route::post('/users/find', [UsuarioController::class, 'findByPublicId']); // Para buscar usuarios por id_publico para invitaciones

Route::middleware('auth:sanctum')->group(function () {
    // Rutas de usuario (ya las tienes)
    Route::get('/user', [UsuarioController::class, 'me']);
    Route::put('/user/update', [UsuarioController::class, 'update']);
    Route::post('/user/logout', [UsuarioController::class, 'logout']);
    Route::get('/user/sync', [UsuarioController::class, 'syncData']); // Sincronización inicial de datos del usuario
    Route::post('/user/sync/update', [UsuarioController::class, 'updateSyncTime']); // Actualizar solo `ultima_sync`
    // Rutas para obtener datos relacionados al usuario (ya las tienes)
    Route::get('/user/groups', [UsuarioController::class, 'myGroups']); // Reemplazado por GrupoController@index
    Route::get('/user/expenses', [UsuarioController::class, 'myExpenses']); // Se puede obtener de GastoController o mantener si la lógica es distinta
    Route::get('/user/conflicts', [UsuarioController::class, 'myConflicts']); // Reemplazado por SyncConflictoController@index

    // Rutas de Grupos
    Route::get('/grupos', [GrupoController::class, 'index'])->name('grupos.index');
    Route::post('/grupos', [GrupoController::class, 'store'])->name('grupos.store');
    Route::get('/grupos/{id}', [GrupoController::class, 'show'])->name('grupos.show');
    Route::put('/grupos/{id}', [GrupoController::class, 'update'])->name('grupos.update');
    Route::delete('/grupos/{id}', [GrupoController::class, 'destroy'])->name('grupos.destroy');
    
    // Rutas para invitaciones y gestión de miembros
    Route::post('/grupos/join', [GrupoController::class, 'joinWithPublicId'])->name('grupos.join');
    Route::post('/grupos/{grupoId}/invitar', [GrupoController::class, 'invitarMiembro'])->name('grupos.invitar');
    Route::post('/grupos/{grupoId}/members', [GrupoController::class, 'addMember'])->name('grupos.members.add');
    Route::delete('/grupos/{grupoId}/members/{usuarioId}', [GrupoController::class, 'removeMember'])->name('grupos.members.remove');

    // Rutas de Gastos
    Route::get('/grupos/{grupoId}/gastos', [GastoController::class, 'index'])->name('gastos.index.grupo'); // Gastos por grupo
    Route::post('/gastos', [GastoController::class, 'store'])->name('gastos.store');
    Route::get('/gastos/{id}', [GastoController::class, 'show'])->name('gastos.show');
    Route::put('/gastos/{id}', [GastoController::class, 'update'])->name('gastos.update'); // PUT para actualizaciones completas o parciales
    Route::delete('/gastos/{id}', [GastoController::class, 'destroy'])->name('gastos.destroy');
    
    // Rutas específicas para pagos y resolución de conflictos
    Route::post('/gastos/{id}/pagar', [GastoController::class, 'marcarPagado'])->name('gastos.pagar');
    Route::post('/gastos/{id}/resolver', [GastoController::class, 'resolverConflicto'])->name('gastos.resolver');
    
    // Rutas de sincronización
    Route::post('/gastos/sync', [GastoController::class, 'sync'])->name('gastos.sync'); // Endpoint para sincronización masiva de gastos

    // Rutas de SyncConflictos
    Route::get('/conflictos', [SyncConflictoController::class, 'index'])->name('conflictos.index');
    Route::get('/conflictos/{id}', [SyncConflictoController::class, 'show'])->name('conflictos.show');
    Route::post('/conflictos/{id}/resolve', [SyncConflictoController::class, 'resolve'])->name('conflictos.resolve');

    // Rutas de AuditLog
    Route::get('/gastos/{gastoId}/history', [AuditLogController::class, 'index'])->name('auditlog.gasto.index');
    
    // Rutas de sincronización global
    Route::post('/sync/push', [UsuarioController::class, 'pushChanges'])->name('sync.push');
    Route::post('/sync/pull', [UsuarioController::class, 'pullChanges'])->name('sync.pull');
    Route::get('/sync/status', [UsuarioController::class, 'syncStatus'])->name('sync.status');
});

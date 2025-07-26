<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\GastoController;
use App\Http\Controllers\SyncConflictoController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\TableroController;
use App\Http\Controllers\TareaController;
use App\Http\Controllers\NotaController;
use App\Http\Controllers\ReporteController;

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
    Route::get('/user/gastos', [UsuarioController::class, 'myExpenses']); // Ruta específica para gastos del usuario
    Route::get('/user/deudas', [UsuarioController::class, 'myDebts']); // Ruta específica para deudas del usuario
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

    // ======= NUEVAS RUTAS PARA TIEMPO REAL =======
    
    // Rutas de Tableros (Trello-like)
    Route::get('/grupos/{grupoId}/tableros', [TableroController::class, 'index'])->name('tableros.index');
    Route::post('/grupos/{grupoId}/tableros', [TableroController::class, 'store'])->name('tableros.store');
    Route::get('/grupos/{grupoId}/tableros/{tableroId}', [TableroController::class, 'show'])->name('tableros.show');
    Route::put('/grupos/{grupoId}/tableros/{tableroId}', [TableroController::class, 'update'])->name('tableros.update');
    Route::delete('/grupos/{grupoId}/tableros/{tableroId}', [TableroController::class, 'destroy'])->name('tableros.destroy');
    Route::post('/grupos/{grupoId}/tableros/reorder', [TableroController::class, 'reorder'])->name('tableros.reorder');

    // Rutas de Tareas
    Route::get('/tableros/{tableroId}/tareas', [TareaController::class, 'index'])->name('tareas.index');
    Route::post('/tableros/{tableroId}/tareas', [TareaController::class, 'store'])->name('tareas.store');
    Route::get('/tareas/{tareaId}', [TareaController::class, 'show'])->name('tareas.show');
    Route::put('/tareas/{tareaId}', [TareaController::class, 'update'])->name('tareas.update');
    Route::delete('/tareas/{tareaId}', [TareaController::class, 'destroy'])->name('tareas.destroy');
    Route::post('/tareas/{tareaId}/move', [TareaController::class, 'move'])->name('tareas.move'); // Mover entre tableros
    Route::post('/tareas/reorder', [TareaController::class, 'reorder'])->name('tareas.reorder');

    // Rutas de Notas colaborativas
    Route::get('/grupos/{grupoId}/notas', [NotaController::class, 'index'])->name('notas.index');
    Route::post('/grupos/{grupoId}/notas', [NotaController::class, 'store'])->name('notas.store');
    Route::get('/notas/{notaId}', [NotaController::class, 'show'])->name('notas.show');
    Route::put('/notas/{notaId}', [NotaController::class, 'update'])->name('notas.update');
    Route::delete('/notas/{notaId}', [NotaController::class, 'destroy'])->name('notas.destroy');
    Route::post('/notas/{notaId}/lock', [NotaController::class, 'lock'])->name('notas.lock'); // Bloquear para edición
    Route::post('/notas/{notaId}/unlock', [NotaController::class, 'unlock'])->name('notas.unlock'); // Liberar bloqueo
    
    // Rutas de Reportes
    Route::get('/reportes/balance/pdf', [ReporteController::class, 'balancePdf'])->name('reportes.balance.pdf');
    Route::get('/reportes/balance/resumen', [ReporteController::class, 'resumenBalance'])->name('reportes.balance.resumen');
});

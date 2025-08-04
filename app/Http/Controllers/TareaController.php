<?php

namespace App\Http\Controllers;

use App\Models\Tarea;
use App\Models\Tablero;
use App\Models\Grupo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TareaController extends Controller
{
    /**
     * Obtener todas las tareas de un tablero
     */
    public function index($tableroId)
    {
        try {
            $user = Auth::user();
            $tablero = Tablero::with('grupo')->find($tableroId);

            if (!$tablero) {
                return response()->json(['success' => false, 'message' => 'Tablero no encontrado.'], 404);
            }

            $grupo = $tablero->grupo;

            // Verificar que el usuario pertenece al grupo
            if (!$grupo->miembros->contains($user->id) && $grupo->creado_por !== $user->id) {
                return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
            }

            $tareas = $tablero->tareas()
                            ->with(['asignado', 'creador'])
                            ->orderBy('orden')
                            ->get();

            return response()->json([
                'success' => true,
                'data' => $tareas
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo tareas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear una nueva tarea
     */
    public function store(Request $request, $tableroId)
    {
        try {
            $user = Auth::user();
            $tablero = Tablero::with('grupo')->find($tableroId);

            if (!$tablero) {
                return response()->json(['success' => false, 'message' => 'Tablero no encontrado.'], 404);
            }

            $grupo = $tablero->grupo;

            // Verificar permisos
            if (!$grupo->miembros->contains($user->id) && $grupo->creado_por !== $user->id) {
                return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'titulo' => 'required|string|max:255',
                'descripcion' => 'nullable|string',
                'prioridad' => 'nullable|in:baja,media,alta,critica',
                'estado' => 'nullable|in:pendiente,en_progreso,completada',
                'asignado_a' => 'nullable|uuid|exists:users,id',
                'fecha_vencimiento' => 'nullable|date',
                'orden' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos.',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verificar que el usuario asignado pertenece al grupo
            if ($request->asignado_a) {
                if (!$grupo->miembros->contains($request->asignado_a) && $grupo->creado_por !== $request->asignado_a) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El usuario asignado debe pertenecer al grupo.'
                    ], 422);
                }
            }

            $tarea = Tarea::create([
                'tablero_id' => $tableroId,
                'grupo_id' => $grupo->id,
                'titulo' => $request->titulo,
                'descripcion' => $request->descripcion,
                'prioridad' => $request->prioridad ?? 'media',
                'estado' => $request->estado ?? 'pendiente',
                'asignado_a' => $request->asignado_a,
                'creado_por' => $user->id,
                'fecha_vencimiento' => $request->fecha_vencimiento,
                'orden' => $request->orden,
            ]);

            $tarea->load(['asignado', 'creador', 'tablero']);

            return response()->json([
                'success' => true,
                'message' => 'Tarea creada exitosamente.',
                'data' => $tarea
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creando tarea: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar una tarea específica
     */
    public function show($tareaId)
    {
        try {
            $user = Auth::user();
            $tarea = Tarea::with(['asignado', 'creador', 'tablero.grupo'])->find($tareaId);

            if (!$tarea) {
                return response()->json(['success' => false, 'message' => 'Tarea no encontrada.'], 404);
            }

            $grupo = $tarea->tablero->grupo;

            // Verificar que el usuario pertenece al grupo
            if (!$grupo->miembros->contains($user->id) && $grupo->creado_por !== $user->id) {
                return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $tarea
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo tarea: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar una tarea
     */
    public function update(Request $request, $tareaId)
    {
        try {
            $user = Auth::user();
            $tarea = Tarea::with(['tablero.grupo'])->find($tareaId);

            if (!$tarea) {
                return response()->json(['success' => false, 'message' => 'Tarea no encontrada.'], 404);
            }

            $grupo = $tarea->tablero->grupo;

            // Verificar permisos
            if (!$grupo->miembros->contains($user->id) && $grupo->creado_por !== $user->id) {
                return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'titulo' => 'sometimes|required|string|max:255',
                'descripcion' => 'nullable|string',
                'prioridad' => 'nullable|in:baja,media,alta,critica',
                'estado' => 'nullable|in:pendiente,en_progreso,completada',
                'asignado_a' => 'nullable|uuid|exists:users,id',
                'fecha_vencimiento' => 'nullable|date',
                'orden' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos.',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verificar que el usuario asignado pertenece al grupo
            if ($request->has('asignado_a') && $request->asignado_a) {
                if (!$grupo->miembros->contains($request->asignado_a) && $grupo->creado_por !== $request->asignado_a) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El usuario asignado debe pertenecer al grupo.'
                    ], 422);
                }
            }

            $tarea->update($request->only([
                'titulo', 'descripcion', 'prioridad', 'estado', 
                'asignado_a', 'fecha_vencimiento', 'orden'
            ]));

            $tarea->load(['asignado', 'creador', 'tablero']);

            return response()->json([
                'success' => true,
                'message' => 'Tarea actualizada exitosamente.',
                'data' => $tarea
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error actualizando tarea: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una tarea
     */
    public function destroy($tareaId)
    {
        try {
            $user = Auth::user();
            $tarea = Tarea::with(['tablero.grupo'])->find($tareaId);

            if (!$tarea) {
                return response()->json(['success' => false, 'message' => 'Tarea no encontrada.'], 404);
            }

            $grupo = $tarea->tablero->grupo;

            // Verificar permisos (creador del grupo, creador de la tarea, o asignado)
            if ($grupo->creado_por !== $user->id && 
                $tarea->creado_por !== $user->id && 
                $tarea->asignado_a !== $user->id) {
                return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
            }

            $tarea->delete();

            return response()->json([
                'success' => true,
                'message' => 'Tarea eliminada exitosamente.'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error eliminando tarea: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mover una tarea a otro tablero
     */
    public function move(Request $request, $tareaId)
    {
        try {
            $user = Auth::user();
            $tarea = Tarea::with(['tablero.grupo'])->find($tareaId);

            if (!$tarea) {
                return response()->json(['success' => false, 'message' => 'Tarea no encontrada.'], 404);
            }

            $validator = Validator::make($request->all(), [
                'nuevo_tablero_id' => 'required|uuid|exists:tableros,id',
                'nuevo_orden' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $nuevoTablero = Tablero::with('grupo')->find($request->nuevo_tablero_id);
            
            if (!$nuevoTablero) {
                return response()->json(['success' => false, 'message' => 'Tablero destino no encontrado.'], 404);
            }

            // Verificar que ambos tableros pertenecen al mismo grupo
            if ($tarea->grupo_id !== $nuevoTablero->grupo_id) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Solo se pueden mover tareas dentro del mismo grupo.'
                ], 422);
            }

            $grupo = $nuevoTablero->grupo;

            // Verificar permisos
            if (!$grupo->miembros->contains($user->id) && $grupo->creado_por !== $user->id) {
                return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
            }

            // Actualizar tablero y orden
            $tarea->update([
                'tablero_id' => $request->nuevo_tablero_id,
                'orden' => $request->nuevo_orden
            ]);

            $tarea->load(['asignado', 'creador', 'tablero']);

            return response()->json([
                'success' => true,
                'message' => 'Tarea movida exitosamente.',
                'data' => $tarea
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error moviendo tarea: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reordenar tareas
     */
    public function reorder(Request $request)
    {
        try {
            $user = Auth::user();

            $validator = Validator::make($request->all(), [
                'tareas' => 'required|array',
                'tareas.*.id' => 'required|uuid|exists:tareas,id',
                'tareas.*.orden' => 'required|integer|min:0',
                'tareas.*.tablero_id' => 'required|uuid|exists:tableros,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos.',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verificar permisos para cada tarea
            foreach ($request->tareas as $tareaData) {
                $tarea = Tarea::with(['tablero.grupo'])->find($tareaData['id']);
                
                if (!$tarea) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tarea no encontrada: ' . $tareaData['id']
                    ], 404);
                }

                $grupo = $tarea->tablero->grupo;

                if (!$grupo->miembros->contains($user->id) && $grupo->creado_por !== $user->id) {
                    return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
                }

                // Actualizar tarea
                $tarea->update([
                    'tablero_id' => $tareaData['tablero_id'],
                    'orden' => $tareaData['orden'],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Tareas reordenadas exitosamente.'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error reordenando tareas: ' . $e->getMessage()
            ], 500);
        }
    }
}

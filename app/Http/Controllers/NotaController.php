<?php

namespace App\Http\Controllers;

use App\Models\Nota;
use App\Models\Grupo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

class NotaController extends Controller
{
    /**
     * Obtener todas las notas de un grupo
     */
    public function index($grupoId)
    {
        try {
            $user = Auth::user();
            $grupo = Grupo::find($grupoId);

            if (!$grupo) {
                return response()->json(['success' => false, 'message' => 'Grupo no encontrado.'], 404);
            }

            // Verificar que el usuario pertenece al grupo
            if (!$grupo->miembros->contains($user->id) && $grupo->creado_por !== $user->id) {
                return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
            }

            // Obtener notas públicas y privadas del usuario
            $notas = Nota::where('grupo_id', $grupoId)
                        ->where(function($query) use ($user) {
                            $query->where('es_privada', false)
                                  ->orWhere(function($subQuery) use ($user) {
                                      $subQuery->where('es_privada', true)
                                               ->where('creado_por', $user->id);
                                  });
                        })
                        ->with(['creador', 'editor'])
                        ->orderBy('updated_at', 'desc')
                        ->get();

            return response()->json([
                'success' => true,
                'data' => $notas
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo notas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear una nueva nota
     */
    public function store(Request $request, $grupoId)
    {
        try {
            $user = Auth::user();
            $grupo = Grupo::find($grupoId);

            if (!$grupo) {
                return response()->json(['success' => false, 'message' => 'Grupo no encontrado.'], 404);
            }

            // Verificar permisos
            if (!$grupo->miembros->contains($user->id) && $grupo->creado_por !== $user->id) {
                return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'titulo' => 'required|string|max:255',
                'contenido' => 'nullable|string',
                'es_privada' => 'nullable|boolean',
                'color' => 'nullable|string|max:7',
                'etiquetas' => 'nullable|array',
                'etiquetas.*' => 'string|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $nota = Nota::create([
                'grupo_id' => $grupoId,
                'titulo' => $request->titulo,
                'contenido' => $request->contenido ?? '',
                'creado_por' => $user->id,
                'ultimo_editor' => $user->id,
                'ultima_edicion' => now(),
                'version' => 1,
                'es_privada' => $request->es_privada ?? false,
                'color' => $request->color ?? '#ffffff',
                'etiquetas' => $request->etiquetas ?? [],
            ]);

            $nota->load(['creador', 'editor']);

            return response()->json([
                'success' => true,
                'message' => 'Nota creada exitosamente.',
                'data' => $nota
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creando nota: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar una nota específica
     */
    public function show($notaId)
    {
        try {
            $user = Auth::user();
            $nota = Nota::with(['creador', 'editor', 'grupo'])->find($notaId);

            if (!$nota) {
                return response()->json(['success' => false, 'message' => 'Nota no encontrada.'], 404);
            }

            $grupo = $nota->grupo;

            // Verificar que el usuario pertenece al grupo
            if (!$grupo->miembros->contains($user->id) && $grupo->creado_por !== $user->id) {
                return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
            }

            // Verificar acceso a nota privada
            if ($nota->es_privada && $nota->creado_por !== $user->id) {
                return response()->json(['success' => false, 'message' => 'No autorizado para ver esta nota privada.'], 403);
            }

            // Verificar si la nota está bloqueada por otro usuario
            $lockKey = "nota_lock_{$notaId}";
            $lockedBy = Cache::get($lockKey);
            
            $nota->locked_by = null;
            $nota->is_locked = false;
            
            if ($lockedBy && $lockedBy !== $user->id) {
                $nota->locked_by = $lockedBy;
                $nota->is_locked = true;
            }

            return response()->json([
                'success' => true,
                'data' => $nota
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo nota: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar una nota
     */
    public function update(Request $request, $notaId)
    {
        try {
            $user = Auth::user();
            $nota = Nota::with(['grupo'])->find($notaId);

            if (!$nota) {
                return response()->json(['success' => false, 'message' => 'Nota no encontrada.'], 404);
            }

            $grupo = $nota->grupo;

            // Verificar que el usuario pertenece al grupo
            if (!$grupo->miembros->contains($user->id) && $grupo->creado_por !== $user->id) {
                return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
            }

            // Verificar acceso a nota privada
            if ($nota->es_privada && $nota->creado_por !== $user->id) {
                return response()->json(['success' => false, 'message' => 'No autorizado para editar esta nota privada.'], 403);
            }

            // Verificar si la nota está bloqueada por otro usuario
            $lockKey = "nota_lock_{$notaId}";
            $lockedBy = Cache::get($lockKey);
            
            if ($lockedBy && $lockedBy !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'La nota está siendo editada por otro usuario.'
                ], 423); // HTTP 423 Locked
            }

            $validator = Validator::make($request->all(), [
                'titulo' => 'sometimes|required|string|max:255',
                'contenido' => 'nullable|string',
                'es_privada' => 'nullable|boolean',
                'color' => 'nullable|string|max:7',
                'etiquetas' => 'nullable|array',
                'etiquetas.*' => 'string|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos.',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Solo el creador puede cambiar la privacidad
            $updateData = $request->only(['titulo', 'contenido', 'color', 'etiquetas']);
            
            if ($request->has('es_privada') && $nota->creado_por === $user->id) {
                $updateData['es_privada'] = $request->es_privada;
            }

            $updateData['ultimo_editor'] = $user->id;

            $nota->update($updateData);
            $nota->load(['creador', 'editor']);

            return response()->json([
                'success' => true,
                'message' => 'Nota actualizada exitosamente.',
                'data' => $nota
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error actualizando nota: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una nota
     */
    public function destroy($notaId)
    {
        try {
            $user = Auth::user();
            $nota = Nota::with(['grupo'])->find($notaId);

            if (!$nota) {
                return response()->json(['success' => false, 'message' => 'Nota no encontrada.'], 404);
            }

            $grupo = $nota->grupo;

            // Verificar permisos (solo creador del grupo o creador de la nota)
            if ($grupo->creado_por !== $user->id && $nota->creado_por !== $user->id) {
                return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
            }

            // Liberar el bloqueo si existe
            $lockKey = "nota_lock_{$notaId}";
            Cache::forget($lockKey);

            $nota->delete();

            return response()->json([
                'success' => true,
                'message' => 'Nota eliminada exitosamente.'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error eliminando nota: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bloquear una nota para edición
     */
    public function lock($notaId)
    {
        try {
            $user = Auth::user();
            $nota = Nota::with(['grupo'])->find($notaId);

            if (!$nota) {
                return response()->json(['success' => false, 'message' => 'Nota no encontrada.'], 404);
            }

            $grupo = $nota->grupo;

            // Verificar que el usuario pertenece al grupo
            if (!$grupo->miembros->contains($user->id) && $grupo->creado_por !== $user->id) {
                return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
            }

            // Verificar acceso a nota privada
            if ($nota->es_privada && $nota->creado_por !== $user->id) {
                return response()->json(['success' => false, 'message' => 'No autorizado para bloquear esta nota privada.'], 403);
            }

            $lockKey = "nota_lock_{$notaId}";
            $lockedBy = Cache::get($lockKey);

            if ($lockedBy && $lockedBy !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'La nota ya está bloqueada por otro usuario.',
                    'locked_by' => $lockedBy
                ], 423);
            }

            // Bloquear por 30 minutos
            Cache::put($lockKey, $user->id, 1800); // 30 minutos

            return response()->json([
                'success' => true,
                'message' => 'Nota bloqueada exitosamente.',
                'locked_by' => $user->id,
                'expires_in' => 1800
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error bloqueando nota: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Liberar el bloqueo de una nota
     */
    public function unlock($notaId)
    {
        try {
            $user = Auth::user();
            $nota = Nota::with(['grupo'])->find($notaId);

            if (!$nota) {
                return response()->json(['success' => false, 'message' => 'Nota no encontrada.'], 404);
            }

            $grupo = $nota->grupo;

            // Verificar que el usuario pertenece al grupo
            if (!$grupo->miembros->contains($user->id) && $grupo->creado_por !== $user->id) {
                return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
            }

            $lockKey = "nota_lock_{$notaId}";
            $lockedBy = Cache::get($lockKey);

            // Solo quien bloqueó puede desbloquear (o el creador del grupo)
            if ($lockedBy && $lockedBy !== $user->id && $grupo->creado_por !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo quien bloqueó la nota puede liberarla.'
                ], 403);
            }

            Cache::forget($lockKey);

            return response()->json([
                'success' => true,
                'message' => 'Bloqueo liberado exitosamente.'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error liberando bloqueo: ' . $e->getMessage()
            ], 500);
        }
    }
}

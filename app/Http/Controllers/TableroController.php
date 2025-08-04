<?php

namespace App\Http\Controllers;

use App\Models\Tablero;
use App\Models\Grupo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Events\TableroCreado;
use App\Events\TableroActualizado;
use App\Events\TableroEliminado;

class TableroController extends Controller
{
    /**
     * Obtener todos los tableros de un grupo
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

            $tableros = $grupo->tableros()->with(['tareas' => function($query) {
                $query->with(['asignado', 'creador'])->orderBy('orden');
            }])->orderBy('orden')->get();

            return response()->json([
                'success' => true,
                'data' => $tableros
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo tableros: ' . $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'grupo_id' => $grupoId
            ], 500);
        }
    }

    /**
     * Crear un nuevo tablero
     */
    public function store(Request $request, $grupoId)
    {
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
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'orden' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos.',
                'errors' => $validator->errors()
            ], 422);
        }

        $tablero = Tablero::create([
            'grupo_id' => $grupoId,
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'color' => $request->color ?? '#6B73FF',
            'orden' => $request->orden,
            'creado_por' => $user->id,
        ]);

        $tablero->load(['creador', 'tareas']);

        // Emitir evento WebSocket
        broadcast(new TableroCreado($tablero, $grupoId))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Tablero creado exitosamente.',
            'data' => $tablero
        ], 201);
    }

    /**
     * Reordenar tableros
     */
    public function reorder(Request $request, $grupoId)
    {
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
            'tableros' => 'required|array',
            'tableros.*.id' => 'required|uuid|exists:tableros,id',
            'tableros.*.orden' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos.',
                'errors' => $validator->errors()
            ], 422);
        }

        foreach ($request->tableros as $tableroData) {
            Tablero::where('id', $tableroData['id'])
                   ->where('grupo_id', $grupoId)
                   ->update(['orden' => $tableroData['orden']]);
        }

        $tableros = Tablero::where('grupo_id', $grupoId)
                          ->with(['tareas.asignado', 'tareas.creador'])
                          ->orderBy('orden')
                          ->get();

        return response()->json([
            'success' => true,
            'message' => 'Tableros reordenados exitosamente.',
            'data' => $tableros
        ]);
    }
}

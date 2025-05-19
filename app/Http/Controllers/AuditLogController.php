<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Gasto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogController extends Controller
{
    /**
     * Muestra el historial de auditoría para un gasto específico.
     * Solo los miembros del grupo al que pertenece el gasto pueden ver su historial.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string $gastoId
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, $gastoId)
    {
        $user = Auth::user();
        $gasto = Gasto::with('grupo')->find($gastoId);

        if (!$gasto) {
            return response()->json(['success' => false, 'message' => 'Gasto no encontrado.'], 404);
        }

        // Verificar si el usuario es miembro del grupo del gasto
        if (!$gasto->grupo->miembros->contains($user->id) && $gasto->grupo->creado_por !== $user->id) {
            return response()->json(['success' => false, 'message' => 'No autorizado para ver el historial de este gasto.'], 403);
        }

        $auditLogs = AuditLog::where('gasto_id', $gastoId)
                            ->with('usuario') // El usuario que realizó la acción
                            ->orderBy('timestamp', 'desc')
                            ->get();

        return response()->json(['success' => true, 'data' => $auditLogs]);
    }
}
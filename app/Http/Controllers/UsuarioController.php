<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Grupo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

class UsuarioController extends Controller
{
    /**
     * Registrar un nuevo usuario
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de registro inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'nombre' => $request->nombre,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'id_publico' => Str::uuid()->toString(),
            'ultima_sync' => Carbon::now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Usuario registrado exitosamente',
            'data' => [
                'user' => $user
            ]
        ], 201);
    }

    /**
     * Iniciar sesión de usuario
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|string|email',
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de inicio de sesión inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verificar si el usuario existe
            $user = User::where('email', $request->email)->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            // Verificar contraseña manualmente primero
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contraseña incorrecta'
                ], 401);
            }

            // Intentar autenticación
            if (!Auth::attempt($request->only('email', 'password'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error en Auth::attempt'
                ], 401);
            }

            // Crear token
            $token = $user->createToken('auth_token')->plainTextToken;

            // Actualizar la última sincronización
            $user->ultima_sync = Carbon::now();
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Inicio de sesión exitoso',
                'data' => [
                    'user' => $user,
                    'token' => $token
                ]
            ], 200);

        } catch (\Exception $e) {
            // MOSTRAR ERROR COMPLETO PARA DEBUGGING
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'input_data' => $request->all()
            ], 500);
        }
    }

    /**
     * Cerrar sesión de usuario
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // Revoca el token actual
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada exitosamente'
        ], 200);
    }

    /**
     * Obtener información del usuario autenticado
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()
        ], 200);
    }

    /**
     * Actualizar información del usuario
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id)
            ],
            'password' => 'sometimes|required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de actualización inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        // Actualizar solo los campos que vienen en la solicitud
        if ($request->has('nombre')) {
            $user->nombre = $request->nombre;
        }
        
        if ($request->has('email')) {
            $user->email = $request->email;
        }
        
        if ($request->has('password')) {
            $user->password = Hash::make($request->password);
        }
        
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado exitosamente',
            'data' => $user
        ], 200);
    }

    /**
     * Obtener datos para sincronización inicial
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncData(Request $request)
    {
        $user = $request->user();
        
        // Obtener grupos del usuario (tanto creados como pertenecientes)
        $grupos = $user->grupos()->with(['miembros'])->get();
        $gruposCreados = $user->gruposCreados()->with(['miembros'])->get();
        
        // Combinar y eliminar duplicados
        $todosGrupos = $grupos->merge($gruposCreados)->unique('id');
        
        // Obtener todos los gastos de los grupos
        $gastos = new Collection();
        foreach ($todosGrupos as $grupo) {
            $gastosGrupo = $grupo->gastos()->with(['participantes', 'pagador', 'modificador'])->get();
            $gastos = $gastos->merge($gastosGrupo);
        }
        
        // Obtener conflictos pendientes
        $conflictos = new Collection();
        foreach ($gastos as $gasto) {
            $conflictosGasto = $gasto->conflictos()
                ->where('resuelto', false)
                ->where(function ($query) use ($user) {
                    $query->where('creado_por', $user->id)
                          ->orWhereHas('gasto.participantes', function ($q) use ($user) {
                              $q->where('user_id', $user->id);
                          });
                })
                ->get();
            $conflictos = $conflictos->merge($conflictosGasto);
        }

        // Actualizar la última sincronización
        $user->ultima_sync = Carbon::now();
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Datos sincronizados exitosamente',
            'data' => [
                'user' => $user,
                'grupos' => $todosGrupos,
                'gastos' => $gastos,
                'conflictos' => $conflictos,
                'timestamp_sync' => $user->ultima_sync
            ]
        ], 200);
    }

    /**
     * Buscar usuario por ID público
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function findByPublicId(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_publico' => 'required|string|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'ID público inválido',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('id_publico', $request->id_publico)->first();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        // Devolver información básica, sin datos sensibles
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'nombre' => $user->nombre,
                'id_publico' => $user->id_publico
            ]
        ], 200);
    }

    /**
     * Obtener todos los grupos del usuario
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function myGroups(Request $request)
    {
        $user = $request->user();
        
        $grupos = $user->grupos()->with(['miembros'])->get();
        $gruposCreados = $user->gruposCreados()->with(['miembros'])->get();
        
        // Combinar y eliminar duplicados
        $todosGrupos = $grupos->merge($gruposCreados)->unique('id');
        
        return response()->json([
            'success' => true,
            'data' => $todosGrupos
        ], 200);
    }

    /**
     * Obtener todos los gastos en los que participa el usuario
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function myExpenses(Request $request)
    {
        $user = $request->user();
        
        // Gastos que ha pagado el usuario
        $gastosPagados = $user->gastosPagados()->with(['participantes', 'grupo'])->get();
        
        // Gastos en los que participa el usuario
        $gastosParticipante = $user->gastosParticipante()->with(['pagador', 'grupo'])->get();
        
        // Combinar y eliminar duplicados
        $todosGastos = $gastosPagados->merge($gastosParticipante)->unique('id');
        
        return response()->json([
            'success' => true,
            'data' => $todosGastos
        ], 200);
    }

    /**
     * Obtener deudas pendientes del usuario
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function myDebts(Request $request)
    {
        try {
            $user = $request->user();
            
            // Deudas donde el usuario debe dinero (participante no pagado)
            $deudas = $user->gastosParticipante()
                ->wherePivot('pagado', false)
                ->with(['pagador', 'grupo'])
                ->get()
                ->map(function ($gasto) use ($user) {
                    // Obtener la información del pivot para este usuario específico
                    $pivot = $gasto->participantes()->where('user_id', $user->id)->first();
                    if (!$pivot) return null;
                    
                    return [
                        'gasto_id' => $gasto->id,
                        'gasto_id_publico' => $gasto->id_publico,
                        'descripcion' => $gasto->descripcion,
                        'monto_total' => $gasto->monto,
                        'monto_adeudado' => $pivot->pivot->monto_proporcional,
                        'pagado_por' => $gasto->pagador->nombre,
                        'grupo' => $gasto->grupo->nombre,
                        'fecha_creacion' => $gasto->fecha_creacion,
                    ];
                })->filter(); // Filtrar elementos nulos
            
            // Deudas donde le deben dinero al usuario (gastos pagados por él con participantes no pagados)
            $acreencias = $user->gastosPagados()
                ->with(['grupo'])
                ->get()
                ->map(function ($gasto) use ($user) {
                    // Obtener participantes no pagados para este gasto
                    $participantesNoPagados = $gasto->participantes()
                        ->wherePivot('pagado', false)
                        ->get();
                    
                    if ($participantesNoPagados->isEmpty()) return null;
                    
                    $montoAdeudado = $participantesNoPagados->sum('pivot.monto_proporcional');
                    return [
                        'gasto_id' => $gasto->id,
                        'gasto_id_publico' => $gasto->id_publico,
                        'descripcion' => $gasto->descripcion,
                        'monto_total' => $gasto->monto,
                        'monto_adeudado' => $montoAdeudado,
                        'deudores' => $participantesNoPagados->pluck('nombre'),
                        'grupo' => $gasto->grupo->nombre,
                        'fecha_creacion' => $gasto->fecha_creacion,
                    ];
                })->filter(); // Filtrar elementos nulos
            
            return response()->json([
                'success' => true,
                'data' => [
                    'deudas' => $deudas->values(), // Resetear índices
                    'acreencias' => $acreencias->values(),
                    'resumen' => [
                        'total_deudas' => $deudas->sum('monto_adeudado'),
                        'total_acreencias' => $acreencias->sum('monto_adeudado'),
                        'balance' => $acreencias->sum('monto_adeudado') - $deudas->sum('monto_adeudado')
                    ]
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo deudas: ' . $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Obtener conflictos pendientes del usuario
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function myConflicts(Request $request)
    {
        $user = $request->user();
        
        // Conflictos creados por el usuario
        $conflictosCreados = $user->conflictosCreados()
            ->where('resuelto', false)
            ->with(['gasto.participantes', 'gasto.pagador'])
            ->get();
        
        // Conflictos en gastos donde el usuario participa
        $conflictosParticipante = [];
        $grupos = $user->grupos()->with(['gastos.conflictos'])->get();
        foreach ($grupos as $grupo) {
            foreach ($grupo->gastos as $gasto) {
                if ($gasto->conflictos()->where('resuelto', false)->count() > 0) {
                    $conflictosGasto = $gasto->conflictos()
                        ->where('resuelto', false)
                        ->with(['gasto.participantes', 'gasto.pagador'])
                        ->get();
                    $conflictosParticipante = array_merge($conflictosParticipante, $conflictosGasto->toArray());
                }
            }
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'conflictos_creados' => $conflictosCreados,
                'conflictos_participante' => $conflictosParticipante
            ]
        ], 200);
    }

    /**
     * Actualizar configuración de sincronización
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSyncTime(Request $request)
    {
        $user = $request->user();
        $user->ultima_sync = Carbon::now();
        $user->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Tiempo de sincronización actualizado',
            'data' => [
                'ultima_sync' => $user->ultima_sync
            ]
        ], 200);
    }

    /**
     * Envía cambios locales al servidor (push)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function pushChanges(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'cambios' => 'required|array',
            'cambios.*.tipo' => 'required|in:gasto,grupo,pago',
            'cambios.*.accion' => 'required|in:crear,actualizar,eliminar,pagar',
            'cambios.*.id_publico' => 'required|string',
            'cambios.*.datos' => 'required|array',
            'cambios.*.timestamp_local' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $resultados = [];
            $conflictos = [];

            foreach ($request->cambios as $cambio) {
                switch ($cambio['tipo']) {
                    case 'gasto':
                        $resultado = $this->procesarCambioGasto($cambio, $user);
                        break;
                    case 'grupo':
                        $resultado = $this->procesarCambioGrupo($cambio, $user);
                        break;
                    case 'pago':
                        $resultado = $this->procesarCambioPago($cambio, $user);
                        break;
                    default:
                        continue 2;
                }

                if ($resultado['tipo'] === 'conflicto') {
                    $conflictos[] = $resultado;
                } else {
                    $resultados[] = $resultado;
                }
            }

            // Actualizar última sincronización
            $user->ultima_sync = now();
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Cambios enviados exitosamente.',
                'aplicados' => count($resultados),
                'conflictos' => count($conflictos),
                'datos' => [
                    'resultados' => $resultados,
                    'conflictos' => $conflictos,
                    'ultima_sync' => $user->ultima_sync
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar cambios: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene cambios del servidor (pull)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function pullChanges(Request $request)
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'ultima_sync_local' => 'sometimes|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $ultimaSyncLocal = $request->ultima_sync_local ? 
                Carbon::parse($request->ultima_sync_local) : 
                $user->ultima_sync ?? Carbon::now()->subDays(30);

            // Obtener cambios desde la última sincronización
            $cambios = [
                'gastos' => $this->obtenerGastosCambiados($user, $ultimaSyncLocal),
                'grupos' => $this->obtenerGruposCambiados($user, $ultimaSyncLocal),
                'pagos' => $this->obtenerPagosCambiados($user, $ultimaSyncLocal),
            ];

            // Obtener conflictos pendientes
            $conflictosPendientes = $user->conflictosCreados()
                ->where('estado', 'pendiente')
                ->with(['gasto.participantes', 'gasto.grupo'])
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Cambios obtenidos exitosamente.',
                'datos' => [
                    'cambios' => $cambios,
                    'conflictos_pendientes' => $conflictosPendientes,
                    'timestamp_servidor' => now(),
                    'ultima_sync_usuario' => $user->ultima_sync
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cambios: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene el estado de sincronización
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncStatus()
    {
        $user = Auth::user();

        try {
            $conflictosPendientes = $user->conflictosCreados()
                ->where('estado', 'pendiente')
                ->count();

            $gastosPendientesSync = $user->gastosParticipante()
                ->where('ultima_modificacion', '>', $user->ultima_sync ?? Carbon::now()->subDays(30))
                ->count();

            return response()->json([
                'success' => true,
                'datos' => [
                    'ultima_sync' => $user->ultima_sync,
                    'conflictos_pendientes' => $conflictosPendientes,
                    'gastos_pendientes_sync' => $gastosPendientesSync,
                    'necesita_sincronizacion' => $conflictosPendientes > 0 || $gastosPendientesSync > 0,
                    'timestamp_servidor' => now()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estado de sincronización: ' . $e->getMessage()
            ], 500);
        }
    }

    // Métodos privados para procesamiento de cambios

    private function procesarCambioGasto($cambio, $user)
    {
        // Implementar lógica específica para cambios de gastos
        // Similar a la lógica del sync en GastoController
        return ['tipo' => 'exito', 'id' => $cambio['id_publico']];
    }

    private function procesarCambioGrupo($cambio, $user)
    {
        // Implementar lógica específica para cambios de grupos
        return ['tipo' => 'exito', 'id' => $cambio['id_publico']];
    }

    private function procesarCambioPago($cambio, $user)
    {
        // Implementar lógica específica para cambios de pagos
        return ['tipo' => 'exito', 'id' => $cambio['id_publico']];
    }

    private function obtenerGastosCambiados($user, $ultimaSync)
    {
        // Obtener gastos de grupos donde el usuario es miembro
        $gruposIds = $user->grupos()->pluck('grupos.id')
            ->merge($user->gruposCreados()->pluck('id'));

        return \App\Models\Gasto::whereIn('grupo_id', $gruposIds)
            ->where('ultima_modificacion', '>', $ultimaSync)
            ->with(['pagador', 'participantes', 'grupo'])
            ->get();
    }

    private function obtenerGruposCambiados($user, $ultimaSync)
    {
        // Obtener grupos donde el usuario es miembro que han cambiado
        return $user->grupos()
            ->where('fecha_creacion', '>', $ultimaSync)
            ->orWhere('updated_at', '>', $ultimaSync)
            ->with(['creador', 'miembros'])
            ->get()
            ->merge($user->gruposCreados()
                ->where('fecha_creacion', '>', $ultimaSync)
                ->orWhere('updated_at', '>', $ultimaSync)
                ->with(['creador', 'miembros'])
                ->get());
    }

    private function obtenerPagosCambiados($user, $ultimaSync)
    {
        // Obtener cambios en el estado de pagos
        return $user->gastosParticipante()
            ->wherePivot('updated_at', '>', $ultimaSync)
            ->with(['pagador', 'grupo'])
            ->get();
    }
}
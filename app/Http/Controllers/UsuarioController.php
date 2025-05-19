<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Grupo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales inválidas'
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();
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
}
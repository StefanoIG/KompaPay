<?php

namespace App\Http\Controllers;

use App\Models\Grupo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

class GrupoController extends Controller
{
    /**
     * Muestra una lista de los grupos a los que pertenece el usuario autenticado.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $user = Auth::user(); // Obtiene el usuario autenticado actualmente

        // Grupos donde el usuario es miembro a través de la tabla pivote 'grupo_user'
        $gruposMiembro = $user->grupos()->with(['creador', 'miembros'])->get();

        // Grupos creados por el usuario (donde 'creado_por' es el ID del usuario)
        $gruposCreados = $user->gruposCreados()->with(['creador', 'miembros'])->get();

        // Combinar ambas colecciones y eliminar duplicados
        // (un usuario podría ser creador y también estar explícitamente en la tabla de miembros)
        $todosGrupos = $gruposMiembro->merge($gruposCreados)
            ->unique('id') // Asegura que cada grupo aparezca solo una vez
            ->sortByDesc('fecha_creacion'); // Ordena los grupos

        return response()->json(['success' => true, 'data' => $todosGrupos]);
    }

    /**
     * Almacena un nuevo grupo.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'miembros_ids' => 'sometimes|array', // IDs de usuarios para añadir como miembros iniciales
            'miembros_ids.*' => 'exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Crear el grupo
            $grupo = Grupo::create([
                'nombre' => $request->nombre,
                'creado_por' => $user->id,
                'id_publico' => Str::uuid(),
                'fecha_creacion' => now(),
            ]);

            // Agregar miembros iniciales si se proporcionaron
            if ($request->has('miembros_ids')) {
                $grupo->miembros()->attach($request->miembros_ids);
            }

            // Agregar el creador como miembro automáticamente
            $grupo->miembros()->syncWithoutDetaching([$user->id]);

            // Cargar relaciones para respuesta
            $grupo->load(['creador', 'miembros']);

            return response()->json([
                'success' => true,
                'message' => 'Grupo creado exitosamente',
                'data' => $grupo
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el grupo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Muestra un grupo específico, sus miembros y un resumen de gastos.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $user = Auth::user();
        $grupo = Grupo::with(['creador', 'miembros', 'gastos' => function ($query) {
            $query->with(['pagador', 'participantes'])->latest('ultima_modificacion');
        }])->find($id);

        if (!$grupo) {
            return response()->json(['success' => false, 'message' => 'Grupo no encontrado.'], 404);
        }

        // Verificar si el usuario es miembro del grupo o su creador
        if ($grupo->creado_por !== $user->id && !$grupo->miembros->contains($user->id)) {
            return response()->json(['success' => false, 'message' => 'No autorizado para ver este grupo.'], 403);
        }

        return response()->json(['success' => true, 'data' => $grupo]);
    }

    /**
     * Actualiza la información de un grupo (ej. nombre).
     * Solo el creador del grupo puede actualizarlo.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $grupo = Grupo::find($id);

        if (!$grupo) {
            return response()->json(['success' => false, 'message' => 'Grupo no encontrado.'], 404);
        }

        if ($grupo->creado_por !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Solo el creador puede actualizar el grupo.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Datos de actualización inválidos.', 'errors' => $validator->errors()], 422);
        }

        if ($request->has('nombre')) {
            $grupo->nombre = $request->nombre;
        }
        $grupo->save();

        return response()->json(['success' => true, 'message' => 'Grupo actualizado exitosamente.', 'data' => $grupo->load(['creador', 'miembros'])]);
    }

    /**
     * Elimina un grupo.
     * Solo el creador del grupo puede eliminarlo.
     * Esto también eliminará gastos asociados (debido a onDelete('cascade') en la migración).
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $grupo = Grupo::find($id);

        if (!$grupo) {
            return response()->json(['success' => false, 'message' => 'Grupo no encontrado.'], 404);
        }

        if ($grupo->creado_por !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Solo el creador puede eliminar el grupo.'], 403);
        }

        // Eliminar relaciones de miembros y luego el grupo
        $grupo->miembros()->detach();
        $grupo->delete(); // Los gastos se eliminan en cascada por la BD

        return response()->json(['success' => true, 'message' => 'Grupo eliminado exitosamente.']);
    }

    /**
     * Añade un miembro a un grupo utilizando el id_publico del usuario.
     * Solo el creador del grupo o miembros existentes pueden añadir nuevos miembros.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $grupoId
     * @return \Illuminate\Http\JsonResponse
     */
    public function addMember(Request $request, $grupoId)
    {
        $userInvitador = Auth::user();
        $grupo = Grupo::find($grupoId);

        if (!$grupo) {
            return response()->json(['success' => false, 'message' => 'Grupo no encontrado.'], 404);
        }

        // Verificar si el usuario que invita es creador o miembro
        if ($grupo->creado_por !== $userInvitador->id && !$grupo->miembros->contains($userInvitador->id)) {
            return response()->json(['success' => false, 'message' => 'No autorizado para añadir miembros a este grupo.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'id_publico_usuario' => 'required|string|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'ID público del usuario es requerido.', 'errors' => $validator->errors()], 422);
        }

        $usuarioAAgregar = User::where('id_publico', $request->id_publico_usuario)->first();

        if (!$usuarioAAgregar) {
            return response()->json(['success' => false, 'message' => 'Usuario a agregar no encontrado con el ID público proporcionado.'], 404);
        }

        if ($grupo->miembros->contains($usuarioAAgregar->id) || $grupo->creado_por === $usuarioAAgregar->id) {
            return response()->json(['success' => false, 'message' => 'El usuario ya es miembro de este grupo.'], 409);
        }

        $grupo->miembros()->attach($usuarioAAgregar->id);

        return response()->json(['success' => true, 'message' => 'Usuario agregado al grupo exitosamente.', 'data' => $grupo->load('miembros')]);
    }

    /**
     * Elimina un miembro de un grupo.
     * El creador del grupo puede eliminar a cualquier miembro (excepto a sí mismo si es el último).
     * Los miembros pueden eliminarse a sí mismos.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $grupoId
     * @param  string  $usuarioId (ID del usuario a eliminar)
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeMember(Request $request, $grupoId, $usuarioId)
    {
        $userAutenticado = Auth::user();
        $grupo = Grupo::find($grupoId);

        if (!$grupo) {
            return response()->json(['success' => false, 'message' => 'Grupo no encontrado.'], 404);
        }

        $usuarioAEliminar = User::find($usuarioId);
        if (!$usuarioAEliminar) {
            return response()->json(['success' => false, 'message' => 'Usuario a eliminar no encontrado.'], 404);
        }

        // Verificar si el usuario a eliminar es miembro
        if (!$grupo->miembros->contains($usuarioAEliminar->id) && $grupo->creado_por !== $usuarioAEliminar->id) {
            return response()->json(['success' => false, 'message' => 'El usuario no es miembro de este grupo.'], 404);
        }

        // Lógica de permisos para eliminar:
        // 1. El usuario autenticado es el creador del grupo.
        // 2. El usuario autenticado es el mismo que se quiere eliminar (abandonar grupo).
        if ($userAutenticado->id === $grupo->creado_por || $userAutenticado->id === $usuarioAEliminar->id) {
            // El creador no puede eliminarse a sí mismo si es el único miembro o si aún hay otros miembros y él es el creador.
            // Para que el creador se elimine, implicaría transferir propiedad o que el grupo se quede sin creador (lo que puede ser problemático).
            // Por ahora, el creador no puede autoeliminarse de la lista de miembros si es el creador.
            // Sí puede eliminar a otros. Un miembro sí puede autoeliminarse.
            if ($usuarioAEliminar->id === $grupo->creado_por) {
                return response()->json(['success' => false, 'message' => 'El creador del grupo no puede ser eliminado como miembro de esta forma. Considere eliminar el grupo o transferir la propiedad (funcionalidad no implementada).'], 403);
            }


            // Si quedan otros miembros o si el que se va no es el creador, se permite
            $grupo->miembros()->detach($usuarioAEliminar->id);
            return response()->json(['success' => true, 'message' => 'Miembro eliminado del grupo exitosamente.', 'data' => $grupo->load('miembros')]);
        } else {
            return response()->json(['success' => false, 'message' => 'No autorizado para eliminar a este miembro del grupo.'], 403);
        }
    }

    /**
     * Permite a un usuario unirse a un grupo usando el id_publico del grupo.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function joinWithPublicId(Request $request)
    {
        $user = Auth::user();
        $validator = Validator::make($request->all(), [
            'id_publico_grupo' => 'required|string|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'ID público del grupo es requerido.', 'errors' => $validator->errors()], 422);
        }

        $grupo = Grupo::where('id_publico', $request->id_publico_grupo)->first();

        if (!$grupo) {
            return response()->json(['success' => false, 'message' => 'Grupo no encontrado con el ID público proporcionado.'], 404);
        }

        if ($grupo->miembros->contains($user->id) || $grupo->creado_por === $user->id) {
            return response()->json(['success' => false, 'message' => 'Ya eres miembro de este grupo.'], 409);
        }

        $grupo->miembros()->attach($user->id);

        return response()->json(['success' => true, 'message' => 'Te has unido al grupo exitosamente.', 'data' => $grupo->load(['creador', 'miembros'])]);
    }

    /**
     * Invita a un miembro al grupo por email o ID público de usuario.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $grupoId
     * @return \Illuminate\Http\JsonResponse
     */
    public function invitarMiembro(Request $request, $grupoId)
    {
        $user = Auth::user();
        $grupo = Grupo::find($grupoId);

        if (!$grupo) {
            return response()->json(['success' => false, 'message' => 'Grupo no encontrado.'], 404);
        }

        // Solo miembros del grupo pueden invitar
        if ($grupo->creado_por !== $user->id && !$grupo->miembros->contains($user->id)) {
            return response()->json(['success' => false, 'message' => 'No autorizado para invitar miembros a este grupo.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required_without:id_publico_usuario|email',
            'id_publico_usuario' => 'required_without:email|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            // Buscar usuario por email o ID público
            $usuarioInvitado = null;
            if ($request->has('email')) {
                $usuarioInvitado = User::where('email', $request->email)->first();
            } elseif ($request->has('id_publico_usuario')) {
                $usuarioInvitado = User::where('id_publico', $request->id_publico_usuario)->first();
            }

            if (!$usuarioInvitado) {
                return response()->json(['success' => false, 'message' => 'Usuario no encontrado.'], 404);
            }

            // Verificar si ya es miembro
            if ($grupo->miembros->contains($usuarioInvitado->id) || $grupo->creado_por === $usuarioInvitado->id) {
                return response()->json(['success' => false, 'message' => 'El usuario ya es miembro del grupo.'], 409);
            }

            // Agregar como miembro
            $grupo->miembros()->attach($usuarioInvitado->id);

            return response()->json([
                'success' => true,
                'message' => 'Usuario invitado exitosamente al grupo.',
                'data' => $grupo->load(['creador', 'miembros'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al invitar usuario: ' . $e->getMessage()
            ], 500);
        }
    }
}

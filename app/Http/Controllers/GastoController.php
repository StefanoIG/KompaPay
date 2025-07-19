<?php

namespace App\Http\Controllers;

use App\Models\Gasto;
use App\Models\Grupo;
use App\Models\SyncConflicto;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class GastoController extends Controller
{
    /**
     * Muestra una lista de los gastos de un grupo específico.
     * Solo los miembros del grupo pueden ver los gastos.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string $grupoId
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, $grupoId)
    {
        $user = Auth::user();
        $grupo = Grupo::find($grupoId);

        if (!$grupo) {
            return response()->json(['success' => false, 'message' => 'Grupo no encontrado.'], 404);
        }

        // Verificar si el usuario es miembro del grupo
        if (!$grupo->miembros->contains($user->id) && $grupo->creado_por !== $user->id) {
            return response()->json(['success' => false, 'message' => 'No autorizado para ver los gastos de este grupo.'], 403);
        }

        $gastos = $grupo->gastos()->with(['pagador', 'modificador', 'participantes'])->latest('ultima_modificacion')->get();

        return response()->json(['success' => true, 'data' => $gastos]);
    }

    /**
     * Almacena un nuevo gasto.
     * Este método será utilizado tanto para la creación online directa como
     * para procesar gastos creados offline y enviados por el cliente.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $validator = Validator::make($request->all(), [
            'id' => 'sometimes|uuid', // ID generado por el cliente para gastos offline
            'grupo_id' => 'required|uuid|exists:grupos,id',
            'descripcion' => 'required|string|max:255',
            'monto_total' => 'required|numeric|min:0.01',
            'pagado_por' => 'required|uuid|exists:users,id',
            'participantes' => 'required|array',
            'participantes.*.id_usuario' => 'required|uuid|exists:users,id',
            'participantes.*.monto_proporcional' => 'required|numeric|min:0.01',
            'estado_pago' => 'sometimes|in:pendiente,pagado',
            'ultima_modificacion' => 'required|date_format:Y-m-d H:i:s',
            'modificado_por' => 'required|uuid|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Datos del gasto inválidos.', 'errors' => $validator->errors()], 422);
        }

        $grupo = Grupo::find($request->grupo_id);

        // Verificar que el usuario que paga y el que modifica sean miembros del grupo
        if (!$grupo || (!$grupo->miembros->contains($user->id) && $grupo->creado_por !== $user->id)) {
            return response()->json(['success' => false, 'message' => 'No perteneces al grupo especificado.'], 403);
        }
        if (!$grupo->miembros->contains($request->pagado_por) && $grupo->creado_por !== $request->pagado_por) {
             return response()->json(['success' => false, 'message' => 'El usuario que pagó no es miembro del grupo.'], 422);
        }
        if (!$grupo->miembros->contains($request->modificado_por) && $grupo->creado_por !== $request->modificado_por ) {
             return response()->json(['success' => false, 'message' => 'El usuario modificador no es miembro del grupo.'], 422);
        }


        // Validar que la suma de los montos proporcionales sea igual al monto total
        $sumaParticipantes = array_reduce($request->participantes, function ($carry, $item) {
            return $carry + $item['monto_proporcional'];
        }, 0);

        if (abs($sumaParticipantes - $request->monto_total) > 0.001) { // Tolerancia para errores de punto flotante
            return response()->json(['success' => false, 'message' => 'La suma de los montos proporcionales no coincide con el monto total.'], 422);
        }

        // Validar que todos los participantes sean miembros del grupo
        foreach ($request->participantes as $participante) {
            if (!$grupo->miembros->contains($participante['id_usuario']) && $grupo->creado_por !== $participante['id_usuario']) {
                return response()->json(['success' => false, 'message' => "El usuario {$participante['id_usuario']} no es miembro del grupo."], 422);
            }
        }

        // Si el cliente envía un ID, es un gasto creado offline
        $gastoId = $request->input('id', (string) Str::uuid());
        $accion = 'creacion';

        $gasto = Gasto::find($gastoId);
        if ($gasto) { // Gasto ya existe, podría ser una sincronización tardía o intento de duplicado.
                     // Por ahora, lo tratamos como un error si no es una actualización (ver método update).
                     // O decidimos si es una creación offline que ya llegó por otra vía.
                     // Aquí asumimos que si store es llamado con ID, y existe, es error si no es explícitamente un update.
            return response()->json(['success' => false, 'message' => 'El gasto con este ID ya existe. Para modificarlo, usa la ruta de actualización.'], 409);
        }


        $gasto = new Gasto();
        $gasto->id = $gastoId;
        $gasto->grupo_id = $request->grupo_id;
        $gasto->descripcion = $request->descripcion;
        $gasto->monto = $request->monto_total;
        $gasto->pagado_por = $request->pagado_por;
        //$gasto->estado_pago = $request->input('estado_pago', 'pendiente');
        $gasto->ultima_modificacion = Carbon::parse($request->ultima_modificacion);
        $gasto->modificado_por = $request->modificado_por;
        $gasto->save();

        // Guardar participantes
        $participantesData = [];
        foreach ($request->participantes as $participante) {
            $participantesData[$participante['id_usuario']] = ['monto_proporcional' => $participante['monto_proporcional']];
        }
        $gasto->participantes()->sync($participantesData);

        // Audit Log
        AuditLog::create([
            'gasto_id' => $gasto->id,
            'accion' => $accion,
            'detalle' => $gasto->load(['participantes'])->toJson(),
            'hecho_por' => $user->id,
            'timestamp' => Carbon::now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Gasto creado exitosamente.', 'data' => $gasto->load(['pagador', 'modificador', 'participantes'])], 201);
    }

    /**
     * Muestra un gasto específico.
     *
     * @param  string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $user = Auth::user();
        $gasto = Gasto::with(['pagador', 'modificador', 'participantes', 'grupo'])->find($id);

        if (!$gasto) {
            return response()->json(['success' => false, 'message' => 'Gasto no encontrado.'], 404);
        }

        // Verificar si el usuario es miembro del grupo al que pertenece el gasto
        if (!$gasto->grupo->miembros->contains($user->id) && $gasto->grupo->creado_por !== $user->id) {
            return response()->json(['success' => false, 'message' => 'No autorizado para ver este gasto.'], 403);
        }

        return response()->json(['success' => true, 'data' => $gasto]);
    }

    /**
     * Actualiza un gasto existente.
     * Maneja la lógica de conflictos.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user(); // Usuario que está realizando la modificación
        $gasto = Gasto::with('participantes')->find($id);

        if (!$gasto) {
            return response()->json(['success' => false, 'message' => 'Gasto no encontrado.'], 404);
        }

        $grupo = Grupo::find($gasto->grupo_id);
         // Verificar que el usuario que modifica sea miembro del grupo
        if (!$grupo || (!$grupo->miembros->contains($user->id) && $grupo->creado_por !== $user->id)) {
            return response()->json(['success' => false, 'message' => 'No perteneces al grupo de este gasto.'], 403);
        }


        $validator = Validator::make($request->all(), [
            'descripcion' => 'sometimes|required|string|max:255',
            'monto_total' => 'sometimes|required|numeric|min:0.01',
            'pagado_por' => 'sometimes|required|uuid|exists:users,id',
            'participantes' => 'sometimes|required|array',
            'participantes.*.id_usuario' => 'sometimes|required|uuid|exists:users,id',
            'participantes.*.monto_proporcional' => 'sometimes|required|numeric|min:0.01',
            'estado_pago' => 'sometimes|in:pendiente,pagado',
            'ultima_modificacion' => 'required|date_format:Y-m-d H:i:s',
            // modificado_por se toma del usuario autenticado que hace la petición
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Datos de actualización inválidos.', 'errors' => $validator->errors()], 422);
        }

        $timestampCliente = Carbon::parse($request->ultima_modificacion);
        $timestampServidor = Carbon::parse($gasto->ultima_modificacion);

        // Lógica de Conflicto
        // Si la versión del cliente es más antigua o igual Y el contenido es diferente, hay un conflicto.
        // O si la `modificado_por` en el servidor es diferente del usuario actual Y la fecha del cliente no es más reciente.
        $contenidoCambiado = $this->haCambiadoElContenido($gasto, $request->all());

        if ($timestampCliente <= $timestampServidor && $user->id !== $gasto->modificado_por && $contenidoCambiado) {
            // Conflicto detectado
            $conflictoExistente = SyncConflicto::where('gasto_id', $gasto->id)->where('resuelto', false)->first();

            if ($conflictoExistente) {
                // Ya existe un conflicto para este gasto, podría ser que el cliente intente subir una tercera versión
                // o que el conflicto original no se haya resuelto.
                // Por ahora, simplemente informamos que ya hay un conflicto.
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe un conflicto pendiente para este gasto. Resuélvelo primero.',
                    'conflict_id' => $conflictoExistente->id,
                    'data_server' => $gasto,
                    'data_client' => $request->all()
                ], 409); // 409 Conflict
            }

            // Crear nueva entrada de SyncConflicto
            $conflicto = SyncConflicto::create([
                'gasto_id' => $gasto->id,
                'version_a' => $gasto->load('participantes')->toArray(), // Versión del servidor (considerada "A" o la más antigua en disputa)
                'version_b' => array_merge($request->all(), ['modificado_por_original' => $user->id, 'participantes_data' => $request->input('participantes', [])]), // Versión del cliente que causa el conflicto
                'creado_por' => $gasto->pagador->id, // El creador original del gasto (o el pagador)
                'aprobado_por_creador' => true, // Por defecto, se asume que el creador original aprueba su propia versión (o la del servidor si es el primer conflicto)
                'aprobado_por_otro' => false,
                'fecha_conflicto' => Carbon::now(),
            ]);

            // Registrar en AuditLog
            AuditLog::create([
                'gasto_id' => $gasto->id,
                'accion' => 'conflicto_detectado',
                'detalle' => json_encode(['conflicto_id' => $conflicto->id, 'version_servidor' => $conflicto->version_a, 'version_cliente' => $conflicto->version_b]),
                'hecho_por' => $user->id, // Quien intentó la sincronización y causó el conflicto
                'timestamp' => Carbon::now(),
            ]);

            return response()->json([
                'success' => false, // Indicamos que la actualización no se aplicó directamente
                'message' => 'Conflicto detectado al actualizar el gasto. Se ha registrado el conflicto.',
                'conflict_id' => $conflicto->id,
                'data_server' => $gasto, // Devolvemos la versión actual del servidor
                'data_client' => $request->all()
            ], 409); // 409 Conflict
        }

        // Si no hay conflicto, o si el cliente tiene la versión más reciente (timestampCliente > timestampServidor)
        // o si el mismo usuario está modificando su propia entrada consecutivamente.
        $datosOriginales = $gasto->load('participantes')->toJson();

        if ($request->has('descripcion')) $gasto->descripcion = $request->descripcion;
        if ($request->has('monto_total')) $gasto->monto_total = $request->monto_total;
        if ($request->has('pagado_por')) {
            if (!$grupo->miembros->contains($request->pagado_por) && $grupo->creado_por !== $request->pagado_por) {
                 return response()->json(['success' => false, 'message' => 'El nuevo usuario que pagó no es miembro del grupo.'], 422);
            }
            $gasto->pagado_por = $request->pagado_por;
        }
        if ($request->has('estado_pago')) $gasto->estado_pago = $request->estado_pago;

        $gasto->ultima_modificacion = $timestampCliente; // Usar el timestamp del cliente si es una actualización válida
        $gasto->modificado_por = $user->id; // Quien realiza esta modificación
        $gasto->save();

        if ($request->has('participantes')) {
            // Validar que la suma de los montos proporcionales sea igual al monto total
            $sumaParticipantes = array_reduce($request->participantes, function ($carry, $item) {
                return $carry + $item['monto_proporcional'];
            }, 0);
            $montoTotalActual = $request->has('monto_total') ? $request->monto_total : $gasto->monto_total;

            if (abs($sumaParticipantes - $montoTotalActual) > 0.001) {
                return response()->json(['success' => false, 'message' => 'La suma de los montos proporcionales de los participantes no coincide con el monto total del gasto.'], 422);
            }
             // Validar que todos los nuevos participantes sean miembros del grupo
            foreach ($request->participantes as $participante) {
                if (!$grupo->miembros->contains($participante['id_usuario']) && $grupo->creado_por !== $participante['id_usuario']) {
                    return response()->json(['success' => false, 'message' => "El usuario participante {$participante['id_usuario']} no es miembro del grupo."], 422);
                }
            }

            $participantesData = [];
            foreach ($request->participantes as $participante) {
                $participantesData[$participante['id_usuario']] = ['monto_proporcional' => $participante['monto_proporcional']];
            }
            $gasto->participantes()->sync($participantesData);
        }

        // Audit Log
        AuditLog::create([
            'gasto_id' => $gasto->id,
            'accion' => 'modificacion',
            'detalle' => json_encode(['antes' => json_decode($datosOriginales, true), 'despues' => $gasto->load('participantes')->toArray()]),
            'hecho_por' => $user->id,
            'timestamp' => Carbon::now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Gasto actualizado exitosamente.', 'data' => $gasto->load(['pagador', 'modificador', 'participantes'])]);
    }


    /**
     * Función auxiliar para determinar si el contenido del gasto ha cambiado.
     * Compara los campos relevantes del gasto actual con los datos de la solicitud.
     *
     * @param Gasto $gasto El gasto actual en la base de datos.
     * @param array $requestData Los datos provenientes de la solicitud.
     * @return bool True si el contenido ha cambiado, false en caso contrario.
     */
    private function haCambiadoElContenido(Gasto $gasto, array $requestData): bool
    {
        if (isset($requestData['descripcion']) && $gasto->descripcion !== $requestData['descripcion']) {
            return true;
        }
        if (isset($requestData['monto_total']) && (float)$gasto->monto_total !== (float)$requestData['monto_total']) {
            return true;
        }
        if (isset($requestData['pagado_por']) && $gasto->pagado_por !== $requestData['pagado_por']) {
            return true;
        }
        if (isset($requestData['estado_pago']) && $gasto->estado_pago !== $requestData['estado_pago']) {
            return true;
        }

        if (isset($requestData['participantes'])) {
            $participantesActuales = $gasto->participantes->mapWithKeys(function ($p) {
                return [$p->id => (float)$p->pivot->monto_proporcional];
            })->all();

            $participantesNuevos = collect($requestData['participantes'])->mapWithKeys(function ($p) {
                return [$p['id_usuario'] => (float)$p['monto_proporcional']];
            })->all();

            // Comprobar si hay diferencias en los IDs de participantes o en sus montos
            if (count(array_diff_key($participantesActuales, $participantesNuevos)) > 0 ||
                count(array_diff_key($participantesNuevos, $participantesActuales)) > 0) {
                return true;
            }
            foreach ($participantesActuales as $userId => $monto) {
                if (!isset($participantesNuevos[$userId]) || abs($monto - $participantesNuevos[$userId]) > 0.001) {
                    return true;
                }
            }
        }

        return false;
    }


    /**
     * Elimina un gasto.
     * En lugar de una eliminación física inmediata, se podría marcar como eliminado
     * o manejarlo según la lógica de sincronización y auditoría.
     * Por ahora, hacemos una eliminación física para simplificar, pero registramos en auditoría.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $gasto = Gasto::find($id);

        if (!$gasto) {
            return response()->json(['success' => false, 'message' => 'Gasto no encontrado.'], 404);
        }

        $grupo = Grupo::find($gasto->grupo_id);
        // Solo el creador del gasto o el creador del grupo pueden eliminarlo.
        // O un miembro del grupo si se define esa política. Aquí restringimos más.
        if ($gasto->pagado_por !== $user->id && $grupo->creado_por !== $user->id) {
            return response()->json(['success' => false, 'message' => 'No autorizado para eliminar este gasto.'], 403);
        }

        $datosGastoEliminado = $gasto->load('participantes')->toJson();

        // Antes de eliminar, se podrían manejar conflictos pendientes o notificar.
        // Por ahora, eliminamos directamente.
        $gasto->participantes()->detach(); // Eliminar relaciones en tabla pivote
        $gasto->delete();

        // Audit Log
        AuditLog::create([
            'gasto_id' => $id, // El ID del gasto que fue eliminado
            'accion' => 'eliminacion',
            'detalle' => $datosGastoEliminado,
            'hecho_por' => $user->id,
            'timestamp' => Carbon::now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Gasto eliminado exitosamente.']);
    }

    /**
     * Endpoint para la sincronización de múltiples gastos desde el cliente.
     * El cliente envía una lista de gastos creados/modificados offline.
     * Este método itera sobre ellos y llama a store() o update() según corresponda.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sync(Request $request)
    {
        $user = Auth::user();
        $validator = Validator::make($request->all(), [
            'gastos' => 'required|array',
            'gastos.*.id' => 'required|uuid', // El ID local del gasto, puede ser nuevo o existente
            'gastos.*.grupo_id' => 'required|uuid|exists:grupos,id',
            'gastos.*.descripcion' => 'required|string|max:255',
            'gastos.*.monto_total' => 'required|numeric|min:0.01',
            'gastos.*.pagado_por' => 'required|uuid|exists:users,id',
            'gastos.*.participantes' => 'required|array',
            'gastos.*.participantes.*.id_usuario' => 'required|uuid|exists:users,id',
            'gastos.*.participantes.*.monto_proporcional' => 'required|numeric|min:0.01',
            'gastos.*.estado_pago' => 'sometimes|in:pendiente,pagado',
            'gastos.*.ultima_modificacion' => 'required|date_format:Y-m-d H:i:s',
            'gastos.*.modificado_por' => 'required|uuid|exists:users,id', // Quién hizo el cambio en el cliente
            'gastos.*.local_action' => 'required|in:create,update,delete' // Acción realizada offline
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Datos de sincronización inválidos.', 'errors' => $validator->errors()], 422);
        }

        $resultados = [];
        $conflictosGenerados = [];
        $errores = [];

        foreach ($request->gastos as $gastoData) {
            $gastoDataRequest = new Request($gastoData);
            $response = null;
            $idGastoCliente = $gastoData['id'];

            try {
                $grupo = Grupo::find($gastoData['grupo_id']);
                if (!$grupo || (!$grupo->miembros->contains($user->id) && $grupo->creado_por !== $user->id)) {
                     $errores[$idGastoCliente] = ['message' => 'No perteneces al grupo especificado para el gasto ' . $idGastoCliente, 'status' => 403];
                     continue;
                }

                if ($gastoData['local_action'] === 'create') {
                    // Verificar si ya existe por si acaso (ej. doble envío)
                    if (Gasto::find($idGastoCliente)) {
                        // Si ya existe, intentamos tratarlo como una actualización.
                        // Esto es delicado, porque el cliente lo marcó como "create".
                        // Podría ser que el cliente generó un UUID que colisionó (muy improbable)
                        // o que es un reintento y el gasto ya se creó.
                        // La lógica más segura es intentar update si existe.
                        $updateRequest = new Request($gastoData);
                        $response = $this->update($updateRequest, $idGastoCliente);
                    } else {
                         $response = $this->store($gastoDataRequest);
                    }
                } elseif ($gastoData['local_action'] === 'update') {
                    $response = $this->update($gastoDataRequest, $idGastoCliente);
                } elseif ($gastoData['local_action'] === 'delete') {
                    $response = $this->destroy($idGastoCliente); // Asume que destroy devuelve una respuesta JSON
                }

                $responseData = $response ? json_decode($response->getContent(), true) : null;

                if ($responseData) {
                    $resultados[$idGastoCliente] = [
                        'status_code' => $response->getStatusCode(),
                        'success' => $responseData['success'] ?? false,
                        'message' => $responseData['message'] ?? 'Acción procesada.',
                        'data' => $responseData['data'] ?? null,
                    ];
                    if (isset($responseData['conflict_id'])) {
                        $conflictosGenerados[$idGastoCliente] = $responseData['conflict_id'];
                        $resultados[$idGastoCliente]['conflict_id'] = $responseData['conflict_id'];
                    }
                } else {
                     $errores[$idGastoCliente] = ['message' => 'No se pudo procesar la acción para el gasto ' . $idGastoCliente, 'status' => 500];
                }

            } catch (\Exception $e) {
                $errores[$idGastoCliente] = ['message' => $e->getMessage(), 'status' => 500];
                 // Loggear el error en el servidor
                \Illuminate\Support\Facades\Log::error("Error sincronizando gasto {$idGastoCliente}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            }
        }

        // Actualizar `ultima_sync` del usuario si todo fue mayormente exitoso
        // O se puede decidir actualizar siempre y que el cliente maneje los errores individuales
        if (empty($errores) || count($errores) < count($request->gastos)) {
             $authUser = \App\Models\User::find($user->id);
             if ($authUser) {
                 $authUser->ultima_sync = Carbon::now();
                 $authUser->save();
             }
        }

        return response()->json([
            'success' => empty($errores),
            'message' => 'Proceso de sincronización completado.',
            'resultados_gastos' => $resultados,
            'conflictos_generados' => $conflictosGenerados,
            'errores_sincronizacion' => $errores,
            'timestamp_sync_servidor' => $user->ultima_sync
        ]);
    }

    /**
     * Marca un gasto como pagado por el usuario autenticado.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function marcarPagado($id)
    {
        $user = Auth::user();
        $gasto = Gasto::with(['grupo.miembros', 'participantes'])->find($id);

        if (!$gasto) {
            return response()->json(['success' => false, 'message' => 'Gasto no encontrado.'], 404);
        }

        // Verificar que el usuario es participante del gasto
        $participante = $gasto->participantes->where('id', $user->id)->first();
        if (!$participante) {
            return response()->json(['success' => false, 'message' => 'No eres participante de este gasto.'], 403);
        }

        try {
            // Verificar si ya está marcado como pagado
            if ($participante->pivot->pagado) {
                return response()->json(['success' => false, 'message' => 'Ya has marcado tu parte como pagada.'], 409);
            }

            // Marcar como pagado
            $gasto->participantes()->updateExistingPivot($user->id, [
                'pagado' => true,
                'fecha_pago' => now(),
            ]);

            // Registrar en audit log
            AuditLog::create([
                'gasto_id' => $gasto->id,
                'accion' => 'marcado_pagado',
                'hecho_por' => $user->id,
                'datos_anteriores' => json_encode(['pagado' => false]),
                'datos_nuevos' => json_encode(['pagado' => true, 'fecha_pago' => now()]),
                'timestamp' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Gasto marcado como pagado exitosamente.',
                'data' => $gasto->fresh()->load(['pagador', 'participantes'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al marcar el gasto como pagado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resuelve un conflicto de sincronización.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function resolverConflicto(Request $request, $id)
    {
        $user = Auth::user();
        $gasto = Gasto::with(['grupo'])->find($id);

        if (!$gasto) {
            return response()->json(['success' => false, 'message' => 'Gasto no encontrado.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'resolucion' => 'required|in:aceptar_local,aceptar_remoto,mantener_actual',
            'datos_resolucion' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            // Buscar el conflicto relacionado a este gasto
            $conflicto = SyncConflicto::where('gasto_id', $gasto->id)
                ->where('estado', 'pendiente')
                ->first();

            if (!$conflicto) {
                return response()->json(['success' => false, 'message' => 'No se encontró conflicto pendiente para este gasto.'], 404);
            }

            // Verificar autorización para resolver conflicto
            $grupo = $gasto->grupo;
            if ($grupo->creado_por !== $user->id && $conflicto->creado_por !== $user->id) {
                return response()->json(['success' => false, 'message' => 'No autorizado para resolver este conflicto.'], 403);
            }

            // Aplicar resolución
            switch ($request->resolucion) {
                case 'aceptar_local':
                    // Mantener datos actuales del servidor, marcar conflicto como resuelto
                    $conflicto->estado = 'resuelto';
                    $conflicto->resuelto_por = $user->id;
                    $conflicto->fecha_resolucion = now();
                    $conflicto->save();
                    break;

                case 'aceptar_remoto':
                    // Aplicar datos del cliente (guardados en datos_cliente del conflicto)
                    $datosCliente = json_decode($conflicto->datos_cliente, true);
                    
                    if ($datosCliente) {
                        // Actualizar gasto con datos del cliente
                        $gasto->descripcion = $datosCliente['descripcion'] ?? $gasto->descripcion;
                        $gasto->monto = $datosCliente['monto'] ?? $gasto->monto;
                        $gasto->tipo_division = $datosCliente['tipo_division'] ?? $gasto->tipo_division;
                        $gasto->nota = $datosCliente['nota'] ?? $gasto->nota;
                        $gasto->modificado_por = $user->id;
                        $gasto->ultima_modificacion = now();
                        $gasto->save();

                        // Actualizar participantes si están en los datos
                        if (isset($datosCliente['participantes'])) {
                            $gasto->participantes()->detach();
                            foreach ($datosCliente['participantes'] as $participante) {
                                $gasto->participantes()->attach($participante['user_id'], [
                                    'monto_proporcional' => $participante['monto_proporcional'],
                                    'pagado' => $participante['pagado'] ?? false,
                                ]);
                            }
                        }
                    }

                    $conflicto->estado = 'resuelto';
                    $conflicto->resuelto_por = $user->id;
                    $conflicto->fecha_resolucion = now();
                    $conflicto->save();
                    break;

                case 'mantener_actual':
                    // Solo marcar como resuelto sin cambios
                    $conflicto->estado = 'resuelto';
                    $conflicto->resuelto_por = $user->id;
                    $conflicto->fecha_resolucion = now();
                    $conflicto->save();
                    break;
            }

            // Registrar en audit log
            AuditLog::create([
                'gasto_id' => $gasto->id,
                'accion' => 'conflicto_resuelto',
                'hecho_por' => $user->id,
                'datos_anteriores' => json_encode(['resolucion' => $request->resolucion]),
                'datos_nuevos' => json_encode(['conflicto_id' => $conflicto->id]),
                'timestamp' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Conflicto resuelto exitosamente.',
                'data' => $gasto->fresh()->load(['pagador', 'participantes', 'grupo'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al resolver el conflicto: ' . $e->getMessage()
            ], 500);
        }
    }
}
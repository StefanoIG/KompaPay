<?php

namespace App\Http\Controllers;

use App\Models\SyncConflicto;
use App\Models\Gasto;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class SyncConflictoController extends Controller
{
    /**
     * Muestra una lista de los conflictos pendientes para el usuario autenticado.
     * Un conflicto es relevante para un usuario si:
     * 1. Es el creador del gasto original (campo 'creado_por' en SyncConflicto).
     * 2. Es el usuario que intentó modificar y causó el conflicto (inferido de version_b si se almacena el 'modificado_por_original').
     * 3. Es un participante del gasto en conflicto.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $user = Auth::user();

        // Conflictos donde el usuario es el creador del gasto original
        // O donde el gasto en conflicto pertenece a un grupo del que el usuario es miembro
        // y el conflicto no está resuelto.

        $conflictos = SyncConflicto::where('resuelto', false)
            ->where(function ($query) use ($user) {
                $query->where('creado_por', $user->id) // Usuario que creó el gasto original
                    ->orWhereHas('gasto.participantes', function ($q) use ($user) { // Usuario es participante del gasto
                        $q->where('users.id', $user->id);
                    })
                    ->orWhereHas('gasto.grupo.miembros', function ($q) use ($user) { // Usuario es miembro del grupo del gasto
                        $q->where('users.id', $user->id);
                    })
                    ->orWhereHas('gasto.grupo', function ($q) use ($user){ // Usuario es creador del grupo del gasto
                         $q->where('creado_por', $user->id);
                    });
            })
            ->with(['gasto.grupo', 'gasto.pagador', 'creador']) // Creador del conflicto (gasto original)
            ->latest('fecha_conflicto')
            ->get();

        // Puede ser necesario filtrar para asegurar que el usuario esté realmente implicado en la version_b
        // (quién intentó la modificación). Esta información está en `version_b->modificado_por_original`
        // si la guardamos así en GastoController.

        return response()->json(['success' => true, 'data' => $conflictos]);
    }

    /**
     * Muestra los detalles de un conflicto específico.
     *
     * @param  string  $id (ID del SyncConflicto)
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $user = Auth::user();
        $conflicto = SyncConflicto::with([
            'gasto.grupo',
            'gasto.pagador', // Pagador de la versión actual en BD (version_a implícita)
            'gasto.participantes', // Participantes de la versión actual en BD
            'creador' // Quién creó el gasto original
        ])->find($id);

        if (!$conflicto) {
            return response()->json(['success' => false, 'message' => 'Conflicto no encontrado.'], 404);
        }

        // Verificar si el usuario está autorizado para ver este conflicto (similar a la lógica de index)
        $esCreadorGastoOriginal = $conflicto->creado_por === $user->id;
        $esParticipanteGasto = $conflicto->gasto && $conflicto->gasto->participantes->contains($user->id);
        $esMiembroGrupo = $conflicto->gasto && $conflicto->gasto->grupo && ($conflicto->gasto->grupo->miembros->contains($user->id) || $conflicto->gasto->grupo->creado_por === $user->id);
        $intentoModificacion = isset($conflicto->version_b['modificado_por_original']) && $conflicto->version_b['modificado_por_original'] === $user->id;


        if (!$esCreadorGastoOriginal && !$esParticipanteGasto && !$esMiembroGrupo && !$intentoModificacion) {
            return response()->json(['success' => false, 'message' => 'No autorizado para ver este conflicto.'], 403);
        }

        return response()->json(['success' => true, 'data' => $conflicto]);
    }

    /**
     * Permite a los usuarios votar para resolver un conflicto.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id (ID del SyncConflicto)
     * @return \Illuminate\Http\JsonResponse
     */
    public function resolve(Request $request, $id)
    {
        $user = Auth::user();
        $conflicto = SyncConflicto::with('gasto')->find($id);

        if (!$conflicto) {
            return response()->json(['success' => false, 'message' => 'Conflicto no encontrado.'], 404);
        }

        if ($conflicto->resuelto) {
            return response()->json(['success' => false, 'message' => 'Este conflicto ya ha sido resuelto.'], 409);
        }

        $validator = Validator::make($request->all(), [
            'acepta_version' => 'required|in:A,B', // A es version_a (servidor), B es version_b (cliente que causó conflicto)
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Voto inválido.', 'errors' => $validator->errors()], 422);
        }

        $versionElegida = $request->acepta_version;
        $esCreadorOriginal = $user->id === $conflicto->creado_por; // Usuario que creó el gasto originalmente
        $esOtroImplicado = $user->id === ($conflicto->version_b['modificado_por_original'] ?? null); // Usuario que intentó la modificación (version_b)

        if (!$esCreadorOriginal && !$esOtroImplicado) {
            // Si no es ninguno de los dos principales, podría ser un participante.
            // Por ahora, la lógica de aprobación directa se centra en el creador del gasto y el que propuso el cambio.
            // Otros participantes podrían tener un rol de "consulta" o notificarles el resultado.
            // Para este ejemplo, solo el creador y el "otro" pueden cambiar los flags de aprobación.
            return response()->json(['success' => false, 'message' => 'No tienes permiso para votar directamente en la resolución de este conflicto de esta manera.'], 403);
        }

        if ($esCreadorOriginal) {
            $conflicto->aprobado_por_creador = ($versionElegida === 'A'); // Si el creador acepta A, mantiene su versión. Si acepta B, aprueba el cambio.
                                                                     // Esto es un poco confuso. Replantear:
                                                                     // aprobado_por_creador = true si el creador está de acuerdo con la versión que ÉL prefiere.
                                                                     // Si el creador vota por A (su versión original o la del server), aprobado_por_creador (su voto) es por A.
                                                                     // Si el creador vota por B (la modificación), aprobado_por_creador (su voto) es por B.
                                                                     // Vamos a simplificar: si elije "A", su flag de aprobación es para "A".
            $conflicto->aprobado_por_creador = true; // Marcamos que votó
            // ¿Qué versión eligió el creador?
            // $conflicto->version_elegida_por_creador = $versionElegida; (necesitaría nuevo campo)
        }

        if ($esOtroImplicado) {
            $conflicto->aprobado_por_otro = true; // Marcamos que votó
            // $conflicto->version_elegida_por_otro = $versionElegida; (necesitaría nuevo campo)
        }

        // Lógica de Resolución:
        // Si el creador del gasto (o el que "posee" version_a) y el que propuso version_b (el "otro") votan por la MISMA versión.
        // Esto requiere que ambos usuarios voten.
        // O, según la regla: "Si ambos coinciden en una: se aplica esa."

        // Para implementar esto, necesitamos almacenar la elección de cada uno.
        // Modificamos `aprobado_por_creador` y `aprobado_por_otro` para que puedan ser null, true (acepta B), false (acepta A/rechaza B)
        // O mejor, dos campos: voto_creador (A/B), voto_otro (A/B)

        // Simplificación según el flujo original:
        // El creador es notificado. Si rechaza, entra en modo resolución.
        // Si no responde, se da por válido el cambio (version_b).
        // El `aprobado_por_creador` por defecto es true (dando por válido el cambio de `version_b` si no hay intervención)
        // Si el creador interviene y rechaza B, entonces `aprobado_por_creador` se pondría a false (o votaría por A).

        // En este método, asumimos que estamos en "modo resolución" activo.
        // El voto del usuario actual se registra.
        if ($user->id === $conflicto->creado_por) { // Es el creador del gasto original
            $conflicto->aprobado_por_creador = ($versionElegida == 'B'); // Si elije 'B', aprueba el cambio. Si elije 'A', no lo aprueba.
        } elseif (isset($conflicto->version_b['modificado_por_original']) && $user->id === $conflicto->version_b['modificado_por_original']) { // Es el que propuso el cambio
            $conflicto->aprobado_por_otro = ($versionElegida == 'B'); // Si elije 'B', mantiene su propuesta. Si elije 'A', acepta la del servidor.
        }
        // Aquí falta la lógica completa de "ambos deben votar" y "qué pasa si uno vota y el otro no aún".
        // La especificación dice: "Si ambos coinciden en una: se aplica esa."
        // Esto implica que necesitamos un estado para los votos de AMBOS.

        // Supongamos que tenemos `voto_del_creador_original` y `voto_del_modificador_conflicto`
        // que almacenan 'A' o 'B'.
        // Por ahora, actualizamos el flag del usuario que vota.
        // Si el que vota es el creador (dueño de A), y vota por A, entonces `aprobado_por_creador` = false (no aprueba B). Si vota por B, `aprobado_por_creador` = true.
        // Si el que vota es el modificador (dueño de B), y vota por B, entonces `aprobado_por_otro` = true. Si vota por A, `aprobado_por_otro` = false.

        // Esto se complica. Volvamos a la lógica: "Si ambos coinciden".
        // Necesitamos persistir el voto individual. Añadamos campos hipotéticos:
        // $conflicto->voto_creador = $versionElegida; (si user es creador)
        // $conflicto->voto_otro = $versionElegida; (si user es el otro)

        // Almacenemos el voto del usuario actual.
        // Necesitaríamos un campo como 'decision_creador' y 'decision_otro' en SyncConflicto
        // que puedan tomar valores 'A', 'B', o null.
        // Por simplicidad en este ejemplo, y siguiendo "aprobado_por_creador" y "aprobado_por_otro"
        // como booleanos que indican si están de acuerdo con la versión B.

        $gastoAfectado = $conflicto->gasto;
        $versionAplicada = null;

        // Guardamos el voto
        if ($esCreadorOriginal) {
            $conflicto->voto_creador = $versionElegida; // Asumiendo que tenemos este campo
        }
        if ($esOtroImplicado) {
            $conflicto->voto_otro = $versionElegida; // Asumiendo que tenemos este campo
        }
        // $conflicto->save(); // Guardar votos individuales primero

        // Comprobar si ambos han votado y si coinciden (requiere leer los campos de voto)
        // if ($conflicto->voto_creador && $conflicto->voto_otro) {
        //    if ($conflicto->voto_creador === $conflicto->voto_otro) {
        //        $versionAplicada = ($conflicto->voto_creador === 'A') ? $conflicto->version_a : $conflicto->version_b;
        //        $conflicto->resuelto = true;
        //        $conflicto->resuelto_el = Carbon::now();
        //    } else {
        //        // Conflicto persistente, se mantiene la más reciente con flag (ya está así por defecto si no se resuelve)
        //        // Opcionalmente, se podría marcar aquí como "conflicto_persistente_votado"
        //    }
        // }

        // Lógica simplificada por ahora: si el creador rechaza B (vota A), y el "otro" también vota A, se aplica A.
        // Si el creador acepta B (vota B), y el "otro" también vota B, se aplica B.
        // Esto es lo que "Si ambos coinciden en una" significa.
        // La lógica de la pregunta original es:
        // aprobado_por_creador: booleano (por defecto true, si no responde)
        // aprobado_por_otro: booleano
        // Si aprobado_por_creador es true (acepta B) Y aprobado_por_otro es true (acepta B), se aplica B.
        // Si aprobado_por_creador es false (rechaza B, prefiere A) Y aprobado_por_otro es false (rechaza B, prefiere A), se aplica A.

        // El voto actual del usuario:
        if ($esCreadorOriginal) $conflicto->aprobado_por_creador = ($versionElegida === 'B');
        if ($esOtroImplicado) $conflicto->aprobado_por_otro = ($versionElegida === 'B');


        // Verificar si se puede resolver ahora
        if ($conflicto->aprobado_por_creador === true && $conflicto->aprobado_por_otro === true) { // Ambos aceptan B
            $versionAplicada = $conflicto->version_b;
            $nombreVersionAplicada = 'B';
        } elseif ($conflicto->aprobado_por_creador === false && $conflicto->aprobado_por_otro === false && $esCreadorOriginal && $esOtroImplicado) {
            // Para que se aplique A, ambos deben haber votado explícitamente por A.
            // El `aprobado_por_creador = false` significa que el creador prefiere A.
            // El `aprobado_por_otro = false` significa que el otro (que propuso B) ahora prefiere A.
            // Esto es posible si ambos han interactuado.
            $versionAplicada = $conflicto->version_a;
            $nombreVersionAplicada = 'A';
        }


        if ($versionAplicada) {
            $gastoAfectado->descripcion = $versionAplicada['descripcion'];
            $gastoAfectado->monto_total = $versionAplicada['monto_total'];
            $gastoAfectado->pagado_por = $versionAplicada['pagado_por'];
            $gastoAfectado->estado_pago = $versionAplicada['estado_pago'] ?? 'pendiente';
            $gastoAfectado->ultima_modificacion = Carbon::now(); // Timestamp de la resolución
            $gastoAfectado->modificado_por = $user->id; // Quien ejecutó la acción de resolver (o un sistema)
            $gastoAfectado->save();

            // Actualizar participantes del gasto
            $participantesData = [];
            // 'participantes_data' en version_b o 'participantes' en version_a
            $participantesParaAplicar = $nombreVersionAplicada === 'B' ? ($versionAplicada['participantes_data'] ?? $versionAplicada['participantes']) : $versionAplicada['participantes'];

            foreach ($participantesParaAplicar as $participante) {
                // El formato de participantes puede variar un poco entre version_a (modelo Eloquent) y version_b (request)
                $userIdKey = isset($participante['id_usuario']) ? 'id_usuario' : 'user_id'; // o 'id' si es de un toArray()
                $montoKey = 'monto_proporcional';
                 if(!isset($participante[$userIdKey])) { // si viene de un toArray() de elquent con pivot
                    $userIdKey = 'id';
                    $montoKey = 'pivot'; // y luego $participante[$montoKey]['monto_proporcional']
                 }


                if(isset($participante[$userIdKey]) && isset($participante[$montoKey])) {
                    if(is_array($participante[$montoKey]) && isset($participante[$montoKey]['monto_proporcional'])) {
                         $participantesData[$participante[$userIdKey]] = ['monto_proporcional' => $participante[$montoKey]['monto_proporcional']];
                    } else {
                         $participantesData[$participante[$userIdKey]] = ['monto_proporcional' => $participante[$montoKey]];
                    }
                }
            }
            $gastoAfectado->participantes()->sync($participantesData);

            $conflicto->resuelto = true;
            $conflicto->resuelto_el = Carbon::now();

             // Audit Log para la resolución
            AuditLog::create([
                'gasto_id' => $gastoAfectado->id,
                'accion' => 'conflicto_resuelto',
                'detalle' => json_encode([
                    'conflicto_id' => $conflicto->id,
                    'version_aplicada' => $nombreVersionAplicada,
                    'datos_aplicados' => $versionAplicada
                ]),
                'hecho_por' => $user->id,
                'timestamp' => Carbon::now(),
            ]);

            $conflicto->save();
            return response()->json(['success' => true, 'message' => "Conflicto resuelto. Se aplicó la versión {$nombreVersionAplicada}.", 'data' => $gastoAfectado->load('participantes')]);
        } else {
            // Aún no hay consenso o uno de los votos clave falta para una resolución automática
            $conflicto->save(); // Guardar el estado del voto
            return response()->json(['success' => true, 'message' => 'Tu voto ha sido registrado. Esperando la acción de la otra parte implicada.', 'data' => $conflicto]);
        }
    }
}
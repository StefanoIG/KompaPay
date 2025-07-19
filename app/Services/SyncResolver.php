<?php

namespace App\Services;

use App\Models\Gasto;
use App\Models\SyncConflicto;
use App\Models\User;
use App\Models\AuditLog;
use App\Jobs\HandleSyncConflict;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SyncResolver
{
    /**
     * Detecta conflictos de sincronización basados en ID público
     *
     * @param Gasto $gastoExistente
     * @param array $datosNuevos
     * @param User $usuario
     * @return SyncConflicto|null
     */
    public function detectarConflicto($gastoExistente, $datosNuevos, $usuario)
    {
        // Si es el mismo usuario que modificó por última vez, no hay conflicto
        if ($gastoExistente->modificado_por === $usuario->id) {
            return null;
        }

        // Verificar si hay diferencias significativas
        $hayDiferencias = $this->compararDatos($gastoExistente, $datosNuevos);

        if (!$hayDiferencias) {
            return null;
        }

        // Crear conflicto
        return $this->crearConflicto($gastoExistente, $datosNuevos, $usuario, 'diferencias_datos');
    }

    /**
     * Detecta conflictos de concurrencia (edición simultánea)
     *
     * @param Gasto $gasto
     * @param array $datosActualizacion
     * @param User $usuario
     * @return SyncConflicto|null
     */
    public function detectarConflictoConcurrencia($gasto, $datosActualizacion, $usuario)
    {
        // Verificar si el gasto fue modificado recientemente por otro usuario
        $ultimaModificacion = $gasto->ultima_modificacion;
        $umbralConcurrencia = now()->subMinutes(5); // 5 minutos de ventana

        if ($ultimaModificacion > $umbralConcurrencia && $gasto->modificado_por !== $usuario->id) {
            return $this->crearConflicto($gasto, $datosActualizacion, $usuario, 'concurrencia');
        }

        return null;
    }

    /**
     * Detecta conflictos de pago (múltiples usuarios marcando como pagado)
     *
     * @param Gasto $gasto
     * @param User $usuario
     * @return SyncConflicto|null
     */
    public function detectarConflictoPago($gasto, $usuario)
    {
        // Verificar si hay pagos recientes por otros usuarios
        $pagosRecientes = $gasto->participantes()
            ->wherePivot('pagado', true)
            ->wherePivot('fecha_pago', '>', now()->subHours(1))
            ->where('users.id', '!=', $usuario->id)
            ->count();

        if ($pagosRecientes > 0) {
            return $this->crearConflicto($gasto, ['accion' => 'pago'], $usuario, 'conflicto_pago');
        }

        return null;
    }

    /**
     * Resuelve un conflicto aplicando la estrategia especificada
     *
     * @param Gasto $gasto
     * @param string $estrategia
     * @param array|null $datosResolucion
     * @param User $usuario
     * @return array
     */
    public function resolverConflicto($gasto, $estrategia, $datosResolucion, $usuario)
    {
        DB::beginTransaction();

        try {
            $conflicto = SyncConflicto::where('gasto_id', $gasto->id)
                ->where('estado', 'pendiente')
                ->first();

            if (!$conflicto) {
                throw new \Exception('No se encontró conflicto pendiente');
            }

            switch ($estrategia) {
                case 'aceptar_local':
                    $resultado = $this->aplicarResolucionLocal($gasto, $conflicto, $usuario);
                    break;

                case 'aceptar_remoto':
                    $resultado = $this->aplicarResolucionRemota($gasto, $conflicto, $usuario);
                    break;

                case 'manual':
                    $resultado = $this->aplicarResolucionManual($gasto, $conflicto, $datosResolucion, $usuario);
                    break;

                default:
                    throw new \Exception('Estrategia de resolución no válida');
            }

            // Marcar conflicto como resuelto
            $conflicto->update([
                'estado' => 'resuelto',
                'resuelto_por' => $usuario->id,
                'fecha_resolucion' => now(),
                'datos_resolucion' => json_encode([
                    'estrategia' => $estrategia,
                    'datos' => $datosResolucion
                ])
            ]);

            // Registrar en audit log
            AuditLog::create([
                'gasto_id' => $gasto->id,
                'accion' => 'conflicto_resuelto',
                'hecho_por' => $usuario->id,
                'datos_anteriores' => json_encode(['conflicto_id' => $conflicto->id]),
                'datos_nuevos' => json_encode(['estrategia' => $estrategia]),
                'timestamp' => now(),
            ]);

            DB::commit();

            return $resultado;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Procesa sincronización masiva de gastos
     *
     * @param array $gastoData
     * @param User $usuario
     * @return array
     */
    public function procesarSincronizacion($gastoData, $usuario)
    {
        try {
            switch ($gastoData['accion']) {
                case 'crear':
                    return $this->procesarCreacion($gastoData, $usuario);

                case 'actualizar':
                    return $this->procesarActualizacion($gastoData, $usuario);

                case 'eliminar':
                    return $this->procesarEliminacion($gastoData, $usuario);

                default:
                    return [
                        'tipo' => 'error',
                        'mensaje' => 'Acción no válida',
                        'id' => $gastoData['id_publico']
                    ];
            }

        } catch (\Exception $e) {
            return [
                'tipo' => 'error',
                'mensaje' => $e->getMessage(),
                'id' => $gastoData['id_publico']
            ];
        }
    }

    // Métodos privados de apoyo

    private function compararDatos($gastoExistente, $datosNuevos)
    {
        $campos = ['descripcion', 'monto', 'tipo_division', 'nota'];
        
        foreach ($campos as $campo) {
            if (isset($datosNuevos[$campo]) && $gastoExistente->$campo != $datosNuevos[$campo]) {
                return true;
            }
        }

        return false;
    }

    private function crearConflicto($gasto, $datosNuevos, $usuario, $tipo)
    {
        return SyncConflicto::create([
            'gasto_id' => $gasto->id,
            'tipo_conflicto' => $tipo,
            'creado_por' => $usuario->id,
            'datos_servidor' => json_encode($gasto->toArray()),
            'datos_cliente' => json_encode($datosNuevos),
            'estado' => 'pendiente',
            'fecha_creacion' => now(),
        ]);
    }

    private function aplicarResolucionLocal($gasto, $conflicto, $usuario)
    {
        // Mantener datos actuales del servidor
        return [
            'tipo' => 'resolucion_aplicada',
            'estrategia' => 'local',
            'gasto' => $gasto->fresh()->load(['pagador', 'participantes'])
        ];
    }

    private function aplicarResolucionRemota($gasto, $conflicto, $usuario)
    {
        // Aplicar datos del cliente
        $datosCliente = json_decode($conflicto->datos_cliente, true);
        
        if ($datosCliente) {
            $gasto->update([
                'descripcion' => $datosCliente['descripcion'] ?? $gasto->descripcion,
                'monto' => $datosCliente['monto'] ?? $gasto->monto,
                'tipo_division' => $datosCliente['tipo_division'] ?? $gasto->tipo_division,
                'nota' => $datosCliente['nota'] ?? $gasto->nota,
                'modificado_por' => $usuario->id,
                'ultima_modificacion' => now(),
            ]);
        }

        return [
            'tipo' => 'resolucion_aplicada',
            'estrategia' => 'remota',
            'gasto' => $gasto->fresh()->load(['pagador', 'participantes'])
        ];
    }

    private function aplicarResolucionManual($gasto, $conflicto, $datosResolucion, $usuario)
    {
        // Aplicar datos de resolución manual
        if ($datosResolucion) {
            $gasto->update([
                'descripcion' => $datosResolucion['descripcion'] ?? $gasto->descripcion,
                'monto' => $datosResolucion['monto'] ?? $gasto->monto,
                'tipo_division' => $datosResolucion['tipo_division'] ?? $gasto->tipo_division,
                'nota' => $datosResolucion['nota'] ?? $gasto->nota,
                'modificado_por' => $usuario->id,
                'ultima_modificacion' => now(),
            ]);
        }

        return [
            'tipo' => 'resolucion_aplicada',
            'estrategia' => 'manual',
            'gasto' => $gasto->fresh()->load(['pagador', 'participantes'])
        ];
    }

    private function procesarCreacion($gastoData, $usuario)
    {
        // Verificar si ya existe
        $gastoExistente = Gasto::where('id_publico', $gastoData['id_publico'])->first();
        
        if ($gastoExistente) {
            $conflicto = $this->detectarConflicto($gastoExistente, $gastoData['datos'], $usuario);
            
            if ($conflicto) {
                return [
                    'tipo' => 'conflicto',
                    'conflicto_id' => $conflicto->id,
                    'id' => $gastoData['id_publico']
                ];
            }
        }

        // Crear nuevo gasto
        $nuevoGasto = Gasto::create(array_merge($gastoData['datos'], [
            'pagado_por' => $usuario->id,
            'modificado_por' => $usuario->id,
            'id_publico' => $gastoData['id_publico'],
            'fecha_creacion' => now(),
            'ultima_modificacion' => now(),
        ]));

        return [
            'tipo' => 'creado',
            'gasto' => $nuevoGasto,
            'id' => $gastoData['id_publico']
        ];
    }

    private function procesarActualizacion($gastoData, $usuario)
    {
        $gasto = Gasto::where('id_publico', $gastoData['id_publico'])->first();
        
        if (!$gasto) {
            return [
                'tipo' => 'error',
                'mensaje' => 'Gasto no encontrado',
                'id' => $gastoData['id_publico']
            ];
        }

        $conflicto = $this->detectarConflictoConcurrencia($gasto, $gastoData['datos'], $usuario);
        
        if ($conflicto) {
            return [
                'tipo' => 'conflicto',
                'conflicto_id' => $conflicto->id,
                'id' => $gastoData['id_publico']
            ];
        }

        $gasto->update(array_merge($gastoData['datos'], [
            'modificado_por' => $usuario->id,
            'ultima_modificacion' => now(),
        ]));

        return [
            'tipo' => 'actualizado',
            'gasto' => $gasto->fresh(),
            'id' => $gastoData['id_publico']
        ];
    }

    private function procesarEliminacion($gastoData, $usuario)
    {
        $gasto = Gasto::where('id_publico', $gastoData['id_publico'])->first();
        
        if (!$gasto) {
            return [
                'tipo' => 'ya_eliminado',
                'id' => $gastoData['id_publico']
            ];
        }

        // Verificar autorización
        if ($gasto->pagado_por !== $usuario->id && $gasto->grupo->creado_por !== $usuario->id) {
            return [
                'tipo' => 'error',
                'mensaje' => 'No autorizado para eliminar',
                'id' => $gastoData['id_publico']
            ];
        }

        $gasto->delete();

        return [
            'tipo' => 'eliminado',
            'id' => $gastoData['id_publico']
        ];
    }
}

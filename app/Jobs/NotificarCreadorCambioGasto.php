<?php

namespace App\Jobs;

use App\Models\Gasto;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotificarCreadorCambioGasto implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    protected $gasto;
    protected $usuarioQueModifico;

    /**
     * Create a new job instance.
     */
    public function __construct(Gasto $gasto, User $usuarioQueModifico)
    {
        $this->gasto = $gasto;
        $this->usuarioQueModifico = $usuarioQueModifico;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Obtener el creador original del gasto
            $creadorGasto = $this->gasto->pagador;
            
            if (!$creadorGasto) {
                Log::warning("No se pudo notificar cambio en gasto {$this->gasto->id}: creador no encontrado");
                return;
            }

            // No notificar si el que modificó es el mismo creador
            if ($creadorGasto->id === $this->usuarioQueModifico->id) {
                return;
            }

            // Obtener información del grupo
            $grupo = $this->gasto->grupo;
            if (!$grupo) {
                Log::warning("No se pudo notificar cambio en gasto {$this->gasto->id}: grupo no encontrado");
                return;
            }

            // Preparar datos de la notificación
            $datosNotificacion = [
                'tipo' => 'gasto_modificado',
                'gasto_id' => $this->gasto->id,
                'gasto_descripcion' => $this->gasto->descripcion,
                'gasto_monto' => $this->gasto->monto,
                'grupo_nombre' => $grupo->nombre,
                'modificado_por' => $this->usuarioQueModifico->nombre,
                'fecha_modificacion' => $this->gasto->ultima_modificacion,
                'mensaje' => "{$this->usuarioQueModifico->nombre} modificó el gasto '{$this->gasto->descripcion}' en el grupo '{$grupo->nombre}'"
            ];

            // Enviar notificación
            $this->enviarNotificacion($creadorGasto, $datosNotificacion);

            // También notificar al creador del grupo si es diferente
            $creadorGrupo = $grupo->creador;
            if ($creadorGrupo && 
                $creadorGrupo->id !== $creadorGasto->id && 
                $creadorGrupo->id !== $this->usuarioQueModifico->id) {
                
                $this->enviarNotificacion($creadorGrupo, $datosNotificacion);
            }

            Log::info("Notificación de cambio en gasto {$this->gasto->id} enviada exitosamente");

        } catch (\Exception $e) {
            Log::error("Error enviando notificación para gasto {$this->gasto->id}: " . $e->getMessage());
            throw $e; // Re-lanzar para que el job se reintente
        }
    }

    /**
     * Envía la notificación al usuario especificado
     */
    private function enviarNotificacion(User $usuario, array $datos)
    {
        try {
            // Aquí puedes implementar diferentes tipos de notificaciones:
            
            // 1. Notificación en la base de datos (para mostrar en la app)
            $this->crearNotificacionBD($usuario, $datos);

            // 2. Notificación push (si tienes configurado FCM o similar)
            // $this->enviarNotificacionPush($usuario, $datos);

            // 3. Email (si está habilitado)
            // $this->enviarEmail($usuario, $datos);

            Log::info("Notificación enviada al usuario {$usuario->id} sobre gasto {$this->gasto->id}");

        } catch (\Exception $e) {
            Log::error("Error enviando notificación al usuario {$usuario->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Crea una notificación en la base de datos
     */
    private function crearNotificacionBD(User $usuario, array $datos)
    {
        // Crear registro en tabla de notificaciones (puedes crear esta tabla)
        // O usar el sistema de notificaciones de Laravel
        
        // Ejemplo usando notifications table de Laravel:
        /*
        $usuario->notify(new \App\Notifications\GastoModificado([
            'gasto_id' => $datos['gasto_id'],
            'mensaje' => $datos['mensaje'],
            'datos' => $datos
        ]));
        */

        Log::info("Notificación BD creada para usuario {$usuario->id}");
    }

    /**
     * Envía notificación push
     */
    private function enviarNotificacionPush(User $usuario, array $datos)
    {
        // Implementar notificación push usando FCM, Pusher, etc.
        // Ejemplo:
        /*
        if ($usuario->device_token) {
            $pushService = app('push.service');
            $pushService->send($usuario->device_token, [
                'title' => 'Gasto modificado',
                'body' => $datos['mensaje'],
                'data' => $datos
            ]);
        }
        */

        Log::info("Notificación push enviada al usuario {$usuario->id}");
    }

    /**
     * Envía email de notificación
     */
    private function enviarEmail(User $usuario, array $datos)
    {
        // Implementar email usando Mail facade
        // Ejemplo:
        /*
        Mail::to($usuario->email)->send(new \App\Mail\GastoModificado($datos));
        */

        Log::info("Email de notificación enviado al usuario {$usuario->id}");
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Job NotificarCreadorCambioGasto falló para gasto {$this->gasto->id}: " . $exception->getMessage());
    }
}

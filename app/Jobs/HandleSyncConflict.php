<?php

namespace App\Jobs;

use App\Models\SyncConflicto;
use App\Models\Gasto;
use App\Models\User;
use App\Services\SyncResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class HandleSyncConflict implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    protected $conflicto;

    /**
     * Create a new job instance.
     */
    public function __construct(SyncConflicto $conflicto)
    {
        $this->conflicto = $conflicto;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Verificar si el conflicto ya fue resuelto
            if ($this->conflicto->estado !== 'pendiente') {
                Log::info("Conflicto {$this->conflicto->id} ya fue resuelto");
                return;
            }

            // Auto-resolver si han pasado más de 48 horas sin resolución
            $tiempoLimite = $this->conflicto->fecha_creacion->addHours(48);
            
            if (now() > $tiempoLimite) {
                $this->autoResolverConflicto();
                return;
            }

            // Notificar a los usuarios involucrados si no han sido notificados
            $this->notificarUsuarios();

            // Re-programar el job si aún no se resuelve
            $this->release(3600); // Re-intentar en 1 hora

        } catch (\Exception $e) {
            Log::error("Error manejando conflicto {$this->conflicto->id}: " . $e->getMessage());
            
            // Si falla muchas veces, marcar como error
            if ($this->attempts() >= 5) {
                $this->conflicto->update([
                    'estado' => 'error',
                    'datos_resolucion' => json_encode(['error' => $e->getMessage()])
                ]);
            }
        }
    }

    /**
     * Auto-resuelve conflictos después del tiempo límite
     */
    private function autoResolverConflicto()
    {
        try {
            $gasto = $this->conflicto->gasto;
            $grupo = $gasto->grupo ?? null;

            if (!$grupo) {
                Log::warning("No se pudo auto-resolver conflicto {$this->conflicto->id}: grupo no encontrado");
                return;
            }

            // Priorizar al creador del grupo en caso de empate
            $creadorGrupo = User::find($grupo->creado_por);
            
            if ($creadorGrupo) {
                // Resolver a favor del creador del grupo (mantener datos del servidor)
                $this->conflicto->update([
                    'estado' => 'auto_resuelto',
                    'resuelto_por' => $creadorGrupo->id,
                    'fecha_resolucion' => now(),
                    'datos_resolucion' => json_encode([
                        'metodo' => 'auto_resolucion',
                        'razon' => 'timeout_48h',
                        'prioridad' => 'creador_grupo'
                    ])
                ]);

                Log::info("Conflicto {$this->conflicto->id} auto-resuelto a favor del creador del grupo");
            }

        } catch (\Exception $e) {
            Log::error("Error en auto-resolución del conflicto {$this->conflicto->id}: " . $e->getMessage());
        }
    }

    /**
     * Notifica a los usuarios sobre el conflicto pendiente
     */
    private function notificarUsuarios()
    {
        try {
            $gasto = $this->conflicto->gasto;
            $grupo = $gasto->grupo ?? null;

            if (!$grupo) {
                return;
            }

            // Obtener usuarios que deben ser notificados
            $usuariosANotificar = collect();
            
            // Creador del gasto
            if ($gasto->pagador) {
                $usuariosANotificar->push($gasto->pagador);
            }

            // Creador del grupo
            if ($grupo->creador) {
                $usuariosANotificar->push($grupo->creador);
            }

            // Usuario que creó el conflicto
            if ($this->conflicto->creador) {
                $usuariosANotificar->push($this->conflicto->creador);
            }

            // Eliminar duplicados
            $usuariosANotificar = $usuariosANotificar->unique('id');

            foreach ($usuariosANotificar as $usuario) {
                // Aquí podrías enviar notificaciones push, emails, etc.
                Log::info("Notificando conflicto {$this->conflicto->id} al usuario {$usuario->id}");
                
                // Ejemplo: enviar notificación push o email
                // $this->enviarNotificacion($usuario, $this->conflicto);
            }

        } catch (\Exception $e) {
            Log::error("Error notificando conflicto {$this->conflicto->id}: " . $e->getMessage());
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Job HandleSyncConflict falló para conflicto {$this->conflicto->id}: " . $exception->getMessage());
        
        // Marcar el conflicto como error
        $this->conflicto->update([
            'estado' => 'error',
            'datos_resolucion' => json_encode([
                'error' => $exception->getMessage(),
                'failed_at' => now()
            ])
        ]);
    }
}

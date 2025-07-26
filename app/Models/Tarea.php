<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasUuids;

class Tarea extends Model
{
    use HasUuids;

    protected $table = 'tareas';

    protected $fillable = [
        'tablero_id',
        'grupo_id',
        'titulo',
        'descripcion',
        'prioridad',
        'estado',
        'orden',
        'asignado_a',
        'creado_por',
        'fecha_vencimiento',
        'completada_en',
    ];

    protected $casts = [
        'id' => 'string',
        'tablero_id' => 'string',
        'grupo_id' => 'string',
        'asignado_a' => 'string',
        'creado_por' => 'string',
        'fecha_vencimiento' => 'datetime',
        'completada_en' => 'datetime',
    ];

    /**
     * Relación con el tablero
     */
    public function tablero(): BelongsTo
    {
        return $this->belongsTo(Tablero::class, 'tablero_id');
    }

    /**
     * Relación con el grupo
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    /**
     * Relación con el usuario asignado
     */
    public function asignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }

    /**
     * Relación con el usuario creador
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /**
     * Scope para tareas de un tablero específico
     */
    public function scopeDelTablero($query, $tableroId)
    {
        return $query->where('tablero_id', $tableroId)->orderBy('orden');
    }

    /**
     * Scope para tareas por estado
     */
    public function scopePorEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    /**
     * Scope para tareas asignadas a un usuario
     */
    public function scopeAsignadasA($query, $userId)
    {
        return $query->where('asignado_a', $userId);
    }

    /**
     * Boot method para eventos del modelo
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tarea) {
            if (is_null($tarea->orden)) {
                $maxOrden = static::where('tablero_id', $tarea->tablero_id)->max('orden');
                $tarea->orden = ($maxOrden ?? 0) + 1;
            }
        });

        static::updated(function ($tarea) {
            if ($tarea->isDirty('estado') && $tarea->estado === 'completada') {
                $tarea->completada_en = now();
                $tarea->saveQuietly(); // Evitar recursión
            }
        });
    }
}

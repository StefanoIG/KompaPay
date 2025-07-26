<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasUuids;

class Nota extends Model
{
    use HasUuids;

    protected $table = 'notas';

    protected $fillable = [
        'grupo_id',
        'titulo',
        'contenido',
        'creado_por',
        'ultimo_editor',
        'ultima_edicion',
        'version',
        'es_privada',
        'color',
        'etiquetas',
    ];

    protected $casts = [
        'id' => 'string',
        'grupo_id' => 'string',
        'creado_por' => 'string',
        'ultimo_editor' => 'string',
        'ultima_edicion' => 'datetime',
        'es_privada' => 'boolean',
        'etiquetas' => 'array',
    ];

    /**
     * Relación con el grupo
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    /**
     * Relación con el usuario creador
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /**
     * Relación con el último editor
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ultimo_editor');
    }

    /**
     * Scope para notas de un grupo específico
     */
    public function scopeDelGrupo($query, $grupoId)
    {
        return $query->where('grupo_id', $grupoId);
    }

    /**
     * Scope para notas públicas
     */
    public function scopePublicas($query)
    {
        return $query->where('es_privada', false);
    }

    /**
     * Scope para notas privadas de un usuario
     */
    public function scopePrivadasDe($query, $userId)
    {
        return $query->where('es_privada', true)->where('creado_por', $userId);
    }

    /**
     * Boot method para eventos del modelo
     */
    protected static function boot()
    {
        parent::boot();

        static::updating(function ($nota) {
            if ($nota->isDirty('contenido')) {
                $nota->version += 1;
                $nota->ultima_edicion = now();
            }
        });
    }
}

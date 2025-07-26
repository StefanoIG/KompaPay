<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasUuids;

class Tablero extends Model
{
    use HasUuids;

    protected $table = 'tableros';

    protected $fillable = [
        'grupo_id',
        'nombre',
        'descripcion',
        'orden',
        'color',
        'creado_por',
    ];

    protected $casts = [
        'id' => 'string',
        'grupo_id' => 'string',
        'creado_por' => 'string',
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
     * Relación con las tareas
     */
    public function tareas(): HasMany
    {
        return $this->hasMany(Tarea::class, 'tablero_id')->orderBy('orden');
    }

    /**
     * Scope para obtener tableros de un grupo específico
     */
    public function scopeDelGrupo($query, $grupoId)
    {
        return $query->where('grupo_id', $grupoId)->orderBy('orden');
    }

    /**
     * Boot method para eventos del modelo
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tablero) {
            if (is_null($tablero->orden)) {
                $maxOrden = static::where('grupo_id', $tablero->grupo_id)->max('orden');
                $tablero->orden = ($maxOrden ?? 0) + 1;
            }
        });
    }
}

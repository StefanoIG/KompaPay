<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Modelo Grupo
class Grupo extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nombre',
        'creado_por',
        'id_publico',
        'fecha_creacion',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'fecha_creacion' => 'datetime',
    ];

    /**
     * Get the user that created the group.
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /**
     * Get the members of the group.
     */
    public function miembros(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'grupo_user');
    }

    /**
     * Get the expenses of the group.
     */
    public function gastos(): HasMany
    {
        return $this->hasMany(Gasto::class);
    }
}
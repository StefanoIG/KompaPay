<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasUuids;
use App\Models\User;
use App\Models\Gasto;
class SyncConflicto extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'gasto_id',
        'version_a',
        'version_b',
        'creado_por',
        'resuelto',
        'aprobado_por_creador',
        'aprobado_por_otro',
        'fecha_conflicto',
        'resuelto_el',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'version_a' => 'json',
        'version_b' => 'json',
        'resuelto' => 'boolean',
        'aprobado_por_creador' => 'boolean',
        'aprobado_por_otro' => 'boolean',
        'fecha_conflicto' => 'datetime',
        'resuelto_el' => 'datetime',
    ];

    /**
     * Get the expense that has the conflict.
     */
    public function gasto(): BelongsTo
    {
        return $this->belongsTo(Gasto::class);
    }

    /**
     * Get the user who created the original expense.
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'gasto_id',
        'accion',
        'detalle',
        'hecho_por',
        'timestamp',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'detalle' => 'json',
        'timestamp' => 'datetime',
    ];

    /**
     * Get the expense that was affected.
     */
    public function gasto(): BelongsTo
    {
        return $this->belongsTo(Gasto::class);
    }

    /**
     * Get the user who made the action.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hecho_por');
    }
}
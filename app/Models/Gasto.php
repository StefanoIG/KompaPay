<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasUuids;
use App\Models\SyncConflicto;
use App\Models\AuditLog;
use App\Models\Grupo;


class Gasto extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'grupo_id',
        'descripcion',
        'monto_total',
        'pagado_por',
        'estado_pago',
        'ultima_modificacion',
        'modificado_por',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'monto_total' => 'decimal:2',
        'ultima_modificacion' => 'datetime',
    ];

    /**
     * Get the group that the expense belongs to.
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }

    /**
     * Get the user who paid for the expense.
     */
    public function pagador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pagado_por');
    }

    /**
     * Get the user who last modified the expense.
     */
    public function modificador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modificado_por');
    }

    /**
     * Get the participants of the expense.
     */
    public function participantes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'gasto_user')
            ->withPivot('monto_proporcional');
    }

    /**
     * Get the sync conflicts related to this expense.
     */
    public function conflictos(): HasMany
    {
        return $this->hasMany(SyncConflicto::class);
    }

    /**
     * Get the audit logs for this expense.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
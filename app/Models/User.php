<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Grupo;
use App\Models\Gasto;
use App\Models\SyncConflicto;
use App\Models\AuditLog;
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nombre',
        'email',
        'password', // En Laravel, 'password' es el campo para 'clave'
        'id_publico',
        'ultima_sync',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'ultima_sync' => 'datetime',
    ];

    /**
     * Get the groups created by the user.
     */
    public function gruposCreados(): HasMany
    {
        return $this->hasMany(Grupo::class, 'creado_por');
    }

    /**
     * Get the groups the user is a member of.
     */
    public function grupos(): BelongsToMany
    {
        return $this->belongsToMany(Grupo::class, 'grupo_user');
    }

    /**
     * Get the expenses paid by the user.
     */
    public function gastosPagados(): HasMany
    {
        return $this->hasMany(Gasto::class, 'pagado_por');
    }

    /**
     * Get the expenses the user participates in.
     */
    public function gastosParticipante(): BelongsToMany
    {
        return $this->belongsToMany(Gasto::class, 'gasto_user')
            ->withPivot('monto_proporcional', 'pagado', 'fecha_pago')
            ->withTimestamps();
    }

    /**
     * Get the conflicts created by the user.
     */
    public function conflictosCreados(): HasMany
    {
        return $this->hasMany(SyncConflicto::class, 'creado_por');
    }

    /**
     * Get the conflicts resolved by the user.
     */
    public function conflictosResueltos(): HasMany
    {
        return $this->hasMany(SyncConflicto::class, 'resuelto_por');
    }

    /**
     * Get the actions in the audit log done by the user.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'hecho_por');
    }

    /**
     * Get the expenses modified by the user.
     */
    public function gastosModificados(): HasMany
    {
        return $this->hasMany(Gasto::class, 'modificado_por');
    }
}
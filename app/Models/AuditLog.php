<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Scopes\TenantScope;

class AuditLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'audit_logs';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'properties',
        'ip_address',
        'user_agent',
        'url',
        'method',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'properties' => 'array',
    ];

    /**
     * Boot the model and apply global scopes.
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new TenantScope);
    }

    /**
     * Get the tenant that owns the audit log.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user who performed the action.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the auditable model (polymorphic).
     */
    public function auditable()
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to filter by event.
     */
    public function scopeEvent($query, $event)
    {
        return $query->where('event', $event);
    }

    /**
     * Scope a query to filter by auditable.
     */
    public function scopeForModel($query, $model)
    {
        return $query->where('auditable_type', get_class($model))
                     ->where('auditable_id', $model->id);
    }

    /**
     * Scope a query for recent logs.
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}

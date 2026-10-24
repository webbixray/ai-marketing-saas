<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Scopes\TenantScope;

/**
 * Log of AI content generations. Tenant-scoped like Campaign: the global
 * TenantScope limits queries to the authenticated user's tenant (unscoped
 * for tenant-less platform admins; no-op in console).
 */
class AiGeneration extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'type',
        'brief',
        'tone',
        'platform',
        'model',
        'provider',
        'prompt',
        'output',
        'input_tokens',
        'output_tokens',
        'status',
        'error',
    ];

    protected $casts = [
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new TenantScope);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}

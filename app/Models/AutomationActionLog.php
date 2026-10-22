<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Scopes\TenantScope;

class AutomationActionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'rule_id',
        'action_type',
        'params',
        'payload',
        'result',
        'executed_at',
    ];

    protected $casts = [
        'params' => 'array',
        'payload' => 'array',
        'result' => 'array',
        'executed_at' => 'datetime',
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

    public function rule()
    {
        return $this->belongsTo(AutomationRule::class);
    }
}

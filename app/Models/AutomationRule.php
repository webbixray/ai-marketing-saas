<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Scopes\TenantScope;

class AutomationRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'triggers',
        'trigger_filters',
        'conditions',
        'condition_logic',
        'actions',
        'priority',
        'is_active',
        'execution_count',
        'last_executed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'triggers' => 'array',
        'trigger_filters' => 'array',
        'conditions' => 'array',
        'actions' => 'array',
        'is_active' => 'boolean',
        'last_executed_at' => 'datetime',
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function actionLogs()
    {
        return $this->hasMany(AutomationActionLog::class, 'rule_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTrigger($query, string $trigger)
    {
        return $query->whereJsonContains('triggers', $trigger);
    }
}

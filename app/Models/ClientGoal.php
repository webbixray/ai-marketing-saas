<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Scopes\TenantScope;

class ClientGoal extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'name',
        'metric',
        'target_value',
        'current_value',
        'status',
        'target_date',
        'metadata',
    ];

    protected $casts = [
        'target_value' => 'decimal:2',
        'current_value' => 'decimal:2',
        'target_date' => 'date',
        'metadata' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new TenantScope);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function getProgressAttribute(): float
    {
        if ($this->target_value == 0) {
            return 0;
        }
        return min(100, ($this->current_value / $this->target_value) * 100);
    }

    public function getIsAchievedAttribute(): bool
    {
        return $this->current_value >= $this->target_value;
    }
}

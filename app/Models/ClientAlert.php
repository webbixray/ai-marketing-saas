<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Scopes\TenantScope;

class ClientAlert extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'type',
        'severity',
        'message',
        'metadata',
        'acknowledged',
        'acknowledged_by',
        'acknowledged_at',
        'resolved_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'acknowledged' => 'boolean',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
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

    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function scopeUnacknowledged($query)
    {
        return $query->where('acknowledged', false);
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }
}

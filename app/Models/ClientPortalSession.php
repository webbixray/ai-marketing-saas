<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Scopes\TenantScope;

class ClientPortalSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'user_id',
        'ip_address',
        'user_agent',
        'started_at',
        'ended_at',
        'duration_seconds',
        'pages_visited',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'pages_visited' => 'array',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

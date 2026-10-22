<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Scopes\TenantScope;

class ClientCommunication extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'direction',
        'channel',
        'subject',
        'content',
        'user_id',
        'response_at',
        'is_automated',
        'metadata',
    ];

    protected $casts = [
        'response_at' => 'datetime',
        'is_automated' => 'boolean',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Scopes\TenantScope;

class ClientHealthScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'total_score',
        'factors',
        'grade',
        'trend',
    ];

    protected $casts = [
        'total_score' => 'decimal:2',
        'factors' => 'array',
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
}

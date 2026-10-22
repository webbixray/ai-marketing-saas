<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Scopes\TenantScope;

class ClientReport extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'report_type',
        'data',
        'format',
        'status',
        'generated_at',
        'sent_at',
        'viewed_at',
    ];

    protected $casts = [
        'data' => 'array',
        'generated_at' => 'datetime',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
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

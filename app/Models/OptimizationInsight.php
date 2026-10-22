<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Scopes\TenantScope;

class OptimizationInsight extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'insights',
    ];

    protected $casts = [
        'insights' => 'array',
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
}

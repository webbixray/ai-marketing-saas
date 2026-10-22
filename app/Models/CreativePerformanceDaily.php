<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Scopes\TenantScope;

class CreativePerformanceDaily extends Model
{
    use HasFactory;

    protected $fillable = [
        'creative_id',
        'date',
        'impressions',
        'clicks',
        'conversions',
        'spend',
        'ctr',
        'cvr',
        'cpc',
        'cpa',
        'roas',
    ];

    protected $casts = [
        'date' => 'date',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'conversions' => 'integer',
        'spend' => 'decimal:2',
        'ctr' => 'decimal:2',
        'cvr' => 'decimal:2',
        'cpc' => 'decimal:2',
        'cpa' => 'decimal:2',
        'roas' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new TenantScope);
    }

    public function creative()
    {
        return $this->belongsTo(CreativeAsset::class, 'creative_id');
    }
}

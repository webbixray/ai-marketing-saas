<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Scopes\TenantScope;

class Touchpoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'client_id',
        'conversion_event_id',
        'channel',
        'campaign_id',
        'creative_id',
        'occurred_at',
        'cost',
        'metadata',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'cost' => 'decimal:2',
        'metadata' => 'array',
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

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function conversionEvent()
    {
        return $this->belongsTo(ConversionEvent::class, 'conversion_event_id');
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creative()
    {
        return $this->belongsTo(CreativeAsset::class, 'creative_id');
    }
}

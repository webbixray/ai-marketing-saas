<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Scopes\TenantScope;

class ConversionEvent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'client_id',
        'campaign_id',
        'creative_id',
        'user_identifier',
        'conversion_type',
        'conversion_value',
        'currency',
        'converted_at',
        'status',
        'metadata',
    ];

    protected $casts = [
        'conversion_value' => 'decimal:2',
        'converted_at' => 'datetime',
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

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creative()
    {
        return $this->belongsTo(CreativeAsset::class, 'creative_id');
    }

    public function touchpoints()
    {
        return $this->hasMany(Touchpoint::class, 'conversion_event_id');
    }

    public function getAttribution(array $models = ['last_click', 'first_click', 'linear', 'time_decay', 'position_based', 'data_driven']): array
    {
        $attribution = new \App\Services\Intelligence\Analytics\AttributionModeling($this->client);
        return $attribution->compareModels(
            $this->converted_at->copy()->subDays(30),
            $this->converted_at
        );
    }
}

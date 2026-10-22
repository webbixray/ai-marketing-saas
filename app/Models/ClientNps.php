<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Scopes\TenantScope;

class ClientNps extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'score',
        'feedback',
        'survey_date',
        'survey_id',
    ];

    protected $casts = [
        'survey_date' => 'date',
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

    public function getSentimentAttribute(): string
    {
        return match (true) {
            $this->score >= 9 => 'promoter',
            $this->score >= 7 => 'passive',
            default => 'detractor',
        };
    }
}

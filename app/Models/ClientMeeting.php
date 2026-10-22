<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Scopes\TenantScope;

class ClientMeeting extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'meeting_date',
        'type',
        'title',
        'notes',
        'attendees',
        'duration_minutes',
        'status',
    ];

    protected $casts = [
        'meeting_date' => 'date',
        'attendees' => 'array',
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

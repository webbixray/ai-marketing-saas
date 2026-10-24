<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Notification extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The table uses a UUID primary key (not auto-incrementing).
     */
    protected static function booted(): void
    {
        static::creating(function (self $notification): void {
            if (empty($notification->id)) {
                $notification->id = (string) Str::uuid();
            }

            // The table requires tenant_id; inherit it from a User recipient
            // when the caller (e.g. the database notification channel) did
            // not provide it.
            if (empty($notification->tenant_id)
                && $notification->notifiable_type === User::class
                && $notification->notifiable_id
            ) {
                $notification->tenant_id = User::withoutGlobalScopes()
                    ->find($notification->notifiable_id)?->tenant_id;
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'type',
        'notifiable_type',
        'notifiable_id',
        'data',
        'read_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    /**
     * Get the owning notifiable model.
     */
    public function notifiable()
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include unread notifications.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Mark the notification as read.
     *
     * @return $this
     */
    public function markAsRead()
    {
        $this->read_at = now();
        $this->save();

        return $this;
    }
}

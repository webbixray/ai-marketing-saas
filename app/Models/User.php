<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Guard;
use App\Scopes\TenantScope;

use Laravel\Cashier\Billable;
use App\Models\NotificationPreference;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasRoles, Notifiable, Billable, HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
        'avatar',
        'email_verification_token',
        'email_verification_sent_at',
        'password_reset_token',
        'password_reset_sent_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Boot the model and apply tenant scope.
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new TenantScope);
    }

    /**
     * Get the guard name used by the model.
     * @return string
     */
    public function guardName(): string
    {
        return 'api';
    }

    /**
     * Override trait methods to ensure they work correctly.
     */
    protected function getGuardNames()
    {
        return Guard::getNames($this);
    }

    protected function getDefaultGuardName(): string
    {
        return Guard::getDefaultName($this);
    }

    // Public methods to test the protected ones
    public function testGetGuardNames()
    {
        return $this->getGuardNames();
    }

    public function testGetDefaultGuardName()
    {
        return $this->getDefaultGuardName();
    }

    /**
     * Get the user's notification preferences.
     */
    public function notificationPreferences()
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /**
     * Get the user's notifications.
     */
    public function notifications()
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }

    /**
     * Get the user's unread notifications.
     */
    public function getUnreadNotificationsAttribute()
    {
        return $this->notifications()->whereNull('read_at')->get();
    }

    /**
     * Send email verification notification.
     */
    public function sendEmailVerificationNotification()
    {
        $this->update([
            'email_verification_token' => \Illuminate\Support\Str::random(60),
            'email_verification_sent_at' => now(),
        ]);

        $this->notify(new \App\Notifications\VerifyEmail);
    }

    /**
     * Verify email with token.
     */
    public function verifyEmail(string $token): bool
    {
        if ($this->email_verification_token === $token) {
            $this->update([
                'email_verified_at' => now(),
                'email_verification_token' => null,
                'email_verification_sent_at' => null,
            ]);
            return true;
        }
        return false;
    }

    /**
     * Send password reset notification.
     */
    public function sendPasswordResetNotification($token)
    {
        $this->update([
            'password_reset_token' => $token,
            'password_reset_sent_at' => now(),
        ]);

        $this->notify(new \App\Notifications\ResetPassword($token));
    }

    /**
     * Check if email is verified.
     */
    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Get email for verification.
     */
    public function getEmailForVerification(): string
    {
        return $this->email;
    }
}

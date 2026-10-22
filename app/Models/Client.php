<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Scopes\TenantScope;
use App\Traits\Auditable;

class Client extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'email',
        'phone',
        'website',
        'industry',
        'description',
        'branding',
        'social_accounts',
        'ad_accounts',
        'timezone',
        'currency',
        'status',
        'contract_start_date',
        'contract_end_date',
        'monthly_budget',
        'billing_details',
        'account_manager_id',
        'team_members',
        'settings',
        'notes',
        // Health score fields
        'health_score',
        'health_grade',
        'health_trend',
        'health_last_calculated',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'branding' => 'array',
        'social_accounts' => 'array',
        'ad_accounts' => 'array',
        'billing_details' => 'array',
        'team_members' => 'array',
        'settings' => 'array',
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'monthly_budget' => 'decimal:2',
        'health_score' => 'decimal:2',
        'health_last_calculated' => 'datetime',
    ];

    /**
     * Boot the model and apply global scopes.
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new TenantScope);
        static::bootAuditable();
    }

    /**
     * Get the tenant that owns the client.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the account manager for the client.
     */
    public function accountManager()
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    /**
     * Get the campaigns for the client.
     */
    public function campaigns()
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * Scope a query to only include active clients.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get the client's health scores history.
     */
    public function healthScores()
    {
        return $this->hasMany(\App\Models\ClientHealthScore::class);
    }

    /**
     * Get the client's alerts.
     */
    public function alerts()
    {
        return $this->hasMany(\App\Models\ClientAlert::class);
    }

    /**
     * Get the client's reports.
     */
    public function reports()
    {
        return $this->hasMany(\App\Models\ClientReport::class);
    }

    /**
     * Get the client's NPS scores.
     */
    public function npsScores()
    {
        return $this->hasMany(\App\Models\ClientNps::class);
    }

    /**
     * Get the client's goals.
     */
    public function goals()
    {
        return $this->hasMany(\App\Models\ClientGoal::class);
    }

    /**
     * Get the client's meetings.
     */
    public function meetings()
    {
        return $this->hasMany(\App\Models\ClientMeeting::class);
    }

    /**
     * Get the client's communications.
     */
    public function communications()
    {
        return $this->hasMany(\App\Models\ClientCommunication::class);
    }

    /**
     * Get the client's portal sessions.
     */
    public function portalSessions()
    {
        return $this->hasMany(\App\Models\ClientPortalSession::class);
    }

    /**
     * Get the client's support tickets.
     */
    public function supportTickets()
    {
        return $this->hasMany(\App\Models\SupportTicket::class);
    }

    /**
     * Get the latest health score.
     */
    public function getLatestHealthScoreAttribute(): ?array
    {
        return $this->healthScores()->latest()->first()?->toArray();
    }
}

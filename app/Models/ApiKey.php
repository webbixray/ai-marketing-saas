<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Scopes\TenantScope;

class ApiKey extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'key_hash',
        'key_prefix',
        'scopes',
        'ip_whitelist',
        'expires_at',
        'last_used_at',
        'last_used_ip',
        'is_active',
        'description',
    ];

    protected $casts = [
        'scopes' => 'array',
        'ip_whitelist' => 'array',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function hasScope(string $scope): bool
    {
        if (!$this->scopes) {
            return false;
        }
        if (in_array('*', $this->scopes)) {
            return true;
        }
        return in_array($scope, $this->scopes);
    }

    public function isValidForIp(string $ip): bool
    {
        if (!$this->ip_whitelist || empty($this->ip_whitelist)) {
            return true;
        }

        foreach ($this->ip_whitelist as $cidr) {
            if ($this->ipMatchesCidr($ip, $cidr)) {
                return true;
            }
        }
        return false;
    }

    protected function ipMatchesCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }
        [$subnet, $mask] = explode('/', $cidr);
        return (ip2long($ip) & ~((1 << (32 - $mask)) - 1)) === ip2long($subnet);
    }

    public function recordUsage(string $ip): void
    {
        $this->update([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
        ]);
    }

    public static function generateKey(): array
    {
        $prefix = 'wbx_';
        $random = bin2hex(random_bytes(24));
        $rawKey = $prefix . $random;
        $hash = hash('sha256', $rawKey);
        $keyPrefix = substr($rawKey, 0, 12);

        return [
            'raw_key' => $rawKey,
            'key_hash' => $hash,
            'key_prefix' => $keyPrefix,
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }
}

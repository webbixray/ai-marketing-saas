<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

trait Auditable
{
    /**
     * Boot the trait.
     */
    protected static function bootAuditable(): void
    {
        // Use static::created to avoid boot issues
        static::created(function (Model $model) {
            if ($model->shouldAudit()) {
                $model->auditLog('created', null, $model->getAuditData());
            }
        });

        static::updated(function (Model $model) {
            if ($model->shouldAudit() && $model->getDirty()) {
                $model->auditLog('updated', $model->getOriginal(), $model->getChanges());
            }
        });

        static::deleted(function (Model $model) {
            if ($model->shouldAudit()) {
                $model->auditLog('deleted', $model->getAuditData(), null);
            }
        });

        // Only register forceDeleted/restored for models with SoftDeletes
        if (in_array(SoftDeletes::class, class_uses(static::class))) {
            // For force deleted (SoftDeletes)
            static::forceDeleted(function (Model $model) {
                if ($model->shouldAudit()) {
                    $model->auditLog('force_deleted', $model->getAuditData(), null);
                }
            });

            // For restored (SoftDeletes)
            static::restored(function (Model $model) {
                if ($model->shouldAudit()) {
                    $model->auditLog('restored', null, $model->getAuditData());
                }
            });
        }
    }

    /**
     * Determine if the model should be audited.
     */
    protected function shouldAudit(): bool
    {
        // Skip if running in console or testing mode - check env directly to avoid boot issues
        if (php_sapi_name() === 'cli' || (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'testing') || (function_exists('env') && env('APP_ENV') === 'testing')) {
            return false;
        }

        // Skip if no user is authenticated
        if (!Auth::check()) {
            return false;
        }

        // Skip if the user doesn't have a tenant
        $user = Auth::user();
        if (!$user || !$user->tenant_id) {
            return false;
        }

        return true;
    }

    /**
     * Log an audit event.
     */
    protected function auditLog(string $event, ?array $oldValues, ?array $newValues): void
    {
        if (!$this->shouldAudit()) {
            return;
        }

        $user = Auth::user();
        $request = request();

        // Wrap in try-catch to prevent audit failures from breaking the main operation
        try {
            AuditLog::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'event' => class_basename(static::class) . '.' . $event,
                'auditable_type' => static::class,
                'auditable_id' => $this->id,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'properties' => [
                    'model' => static::class,
                ],
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'url' => $request?->fullUrl(),
                'method' => $request?->method(),
            ]);
        } catch (\Throwable $e) {
            // Log but don't throw - audit failure shouldn't break the main operation
            \Illuminate\Support\Facades\Log::error('Audit log failed', [
                'model' => static::class,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the data to audit for the model.
     * Override in model to customize.
     */
    protected function getAuditData(): array
    {
        return $this->attributes;
    }

    /**
     * Get the audit logs for this model.
     */
    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}

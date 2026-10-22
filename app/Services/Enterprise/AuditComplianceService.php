<?php

namespace App\Services\Enterprise;

use App\Models\Tenant;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuditComplianceService
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'enabled' => true,
            'retention_days' => 2555, // 7 years
            'archive_after_days' => 365,
            'encryption_enabled' => true,
            'encryption_key' => '',
            'immutable_logs' => true,
            'real_time_alerts' => true,
            'alert_channels' => ['email', 'slack', 'webhook'],
            'compliance_frameworks' => ['SOC2', 'GDPR', 'HIPAA', 'PCI-DSS'],
            'data_classification' => ['public', 'internal', 'confidential', 'restricted'],
            'access_review_frequency_days' => 90,
            'failed_login_threshold' => 5,
            'failed_login_window_minutes' => 15,
            'privileged_action_logging' => true,
            'data_export_logging' => true,
            'admin_action_logging' => true,
        ], []);
    }

    /**
     * Log an audit event.
     */
    public function logEvent(array $event): array
    {
        $event = array_merge([
            'timestamp' => now()->toISOString(),
            'event_id' => Str::uuid()->toString(),
            'severity' => 'info',
            'category' => 'general',
            'action' => '',
            'resource_type' => '',
            'resource_id' => null,
            'user_id' => null,
            'tenant_id' => null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'request_id' => request()->header('X-Request-ID') ?? Str::uuid()->toString(),
            'metadata' => [],
            'compliance_tags' => [],
            'risk_score' => 0,
        ], $event);

        // Calculate risk score
        $event['risk_score'] = $this->calculateRiskScore($event);

        // Add compliance tags
        $event['compliance_tags'] = $this->getComplianceTags($event);

        // Store in database
        $auditLog = AuditLog::create([
            'event_id' => $event['event_id'],
            'tenant_id' => $event['tenant_id'],
            'user_id' => $event['user_id'],
            'event_type' => $event['category'] . '.' . $event['action'],
            'severity' => $event['severity'],
            'resource_type' => $event['resource_type'],
            'resource_id' => $event['resource_id'],
            'action' => $event['action'],
            'description' => $event['description'] ?? '',
            'ip_address' => $event['ip_address'],
            'user_agent' => $event['user_agent'],
            'request_id' => $event['request_id'],
            'metadata' => $event['metadata'],
            'compliance_tags' => $event['compliance_tags'],
            'risk_score' => $event['risk_score'],
            'old_values' => $event['old_values'] ?? null,
            'new_values' => $event['new_values'] ?? null,
        ]);

        // Check for real-time alerts
        if ($this->config['real_time_alerts']) {
            $this->checkAlerts($auditLog);
        }

        // Check for privileged actions
        if ($this->config['privileged_action_logging'] && $this->isPrivilegedAction($event)) {
            $this->alertPrivilegedAction($auditLog);
        }

        // Check for data exports
        if ($this->config['data_export_logging'] && $this->isDataExport($event)) {
            $this->alertDataExport($auditLog);
        }

        // Check for admin actions
        if ($this->config['admin_action_logging'] && $this->isAdminAction($event)) {
            $this->alertAdminAction($auditLog);
        }

        return $auditLog->toArray();
    }

    /**
     * Log user authentication events.
     */
    public function logAuthEvent(string $action, User $user, bool $success = true, array $metadata = []): array
    {
        return $this->logEvent([
            'category' => 'authentication',
            'action' => $action,
            'severity' => $success ? 'info' : 'warning',
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'resource_type' => 'user',
            'resource_id' => $user->id,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Log data access events.
     */
    public function logDataAccess(User $user, string $resourceType, string $resourceId, string $action, array $metadata = []): array
    {
        return $this->logEvent([
            'category' => 'data_access',
            'action' => $action,
            'severity' => 'info',
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Log data modification events.
     */
    public function logDataModification(User $user, string $resourceType, string $resourceId, string $action, array $oldValues, array $newValues): array
    {
        return $this->logEvent([
            'category' => 'data_modification',
            'action' => $action,
            'severity' => 'info',
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    /**
     * Log security events.
     */
    public function logSecurityEvent(string $action, string $description, string $severity = 'warning', array $metadata = []): array
    {
        $user = auth()->user();
        
        return $this->logEvent([
            'category' => 'security',
            'action' => $action,
            'severity' => $severity,
            'description' => $description,
            'user_id' => $user?->id,
            'tenant_id' => $user?->tenant_id,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Log compliance events.
     */
    public function logComplianceEvent(string $framework, string $action, string $description, array $metadata = []): array
    {
        $user = auth()->user();
        
        return $this->logEvent([
            'category' => 'compliance',
            'action' => $action,
            'severity' => 'info',
            'description' => $description,
            'user_id' => $user?->id,
            'tenant_id' => $user?->tenant_id,
            'metadata' => array_merge($metadata, ['framework' => $framework]),
            'compliance_tags' => [$framework],
        ]);
    }

    /**
     * Query audit logs with filters.
     */
    public function queryLogs(array $filters = [], array $pagination = []): array
    {
        $query = AuditLog::query();

        // Apply filters
        if (!empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (!empty($filters['resource_type'])) {
            $query->where('resource_type', $filters['resource_type']);
        }

        if (!empty($filters['resource_id'])) {
            $query->where('resource_id', $filters['resource_id']);
        }

        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        if (!empty($filters['severity_min'])) {
            $severityOrder = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3, 'critical' => 4];
            $minLevel = $severityOrder[$filters['severity_min']] ?? 0;
            $query->where(function ($q) use ($severityOrder, $minLevel) {
                foreach ($severityOrder as $severity => $level) {
                    if ($level >= $minLevel) {
                        $q->orWhere('severity', $severity);
                    }
                }
            });
        }

        if (!empty($filters['risk_score_min'])) {
            $query->where('risk_score', '>=', $filters['risk_score_min']);
        }

        if (!empty($filters['compliance_tags'])) {
            $query->whereJsonContains('compliance_tags', $filters['compliance_tags']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('description', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('metadata', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('request_id', 'LIKE', "%{$filters['search']}%");
            });
        }

        // Apply sorting
        $sortBy = $pagination['sort_by'] ?? 'created_at';
        $sortOrder = $pagination['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Apply pagination
        $perPage = min($pagination['per_page'] ?? 50, 1000);
        $page = $pagination['page'] ?? 1;

        $results = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $results->items(),
            'pagination' => [
                'total' => $results->total(),
                'per_page' => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
            ],
        ];
    }

    /**
     * Generate compliance report.
     */
    public function generateComplianceReport(string $framework, Carbon $startDate, Carbon $endDate, array $options = []): array
    {
        $report = [
            'framework' => $framework,
            'period' => [
                'start' => $startDate->toISOString(),
                'end' => $endDate->toISOString(),
            ],
            'generated_at' => now()->toISOString(),
            'summary' => [],
            'findings' => [],
            'recommendations' => [],
        ];

        // Get all audit logs for period
        $logs = AuditLog::where('tenant_id', auth()->user()->tenant_id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereJsonContains('compliance_tags', $framework)
            ->get();

        $report['summary']['total_events'] = $logs->count();
        $report['summary']['by_severity'] = $logs->groupBy('severity')->map->count()->toArray();
        $report['summary']['by_category'] = $logs->groupBy('category')->map->count()->toArray();
        $report['summary']['by_action'] = $logs->groupBy('action')->map->count()->toArray();
        $report['summary']['unique_users'] => $logs->pluck('user_id')->unique()->count();
        $report['summary']['high_risk_events'] => $logs->where('risk_score', '>=', 70)->count();

        // Framework-specific checks
        $report['findings'] = $this->runComplianceChecks($framework, $logs);
        
        // Generate recommendations
        $report['recommendations'] = $this->generateRecommendations($framework, $logs);

        // Store report
        $reportId = Str::uuid()->toString();
        Cache::put("compliance_report_{$reportId}", $report, 86400 * 30); // 30 days

        return array_merge($report, ['report_id' => $reportId]);
    }

    /**
     * Run compliance checks for specific framework.
     */
    protected function runComplianceChecks(string $framework, Collection $logs): array
    {
        $findings = [];

        switch ($framework) {
            case 'SOC2':
                $findings = $this->checkSoc2Controls($logs);
                break;
            case 'GDPR':
                $findings = $this->checkGdprControls($logs);
                break;
            case 'HIPAA':
                $findings = $this->checkHipaaControls($logs);
                break;
            case 'PCI-DSS':
                $findings = $this->checkPciDssControls($logs);
                break;
        }

        return $findings;
    }

    /**
     * SOC 2 compliance checks.
     */
    protected function checkSoc2Controls(Collection $logs): array
    {
        $findings = [];

        // CC6.1 - Logical access controls
        $failedLogins = $this->getFailedLoginAttempts();
        if ($failedLogins > 100) {
            $findings[] = [
                'control' => 'CC6.1',
                'title' => 'Excessive Failed Login Attempts',
                'description' => "Detected {$failedLogins} failed login attempts in the period",
                'severity' => 'high',
                'evidence' => ['failed_logins' => $failedLogins],
            ];
        }

        // CC6.7 - Data transmission protection
        $unencryptedTransfers = $this->checkUnencryptedTransfers();
        if ($unencryptedTransfers > 0) {
            $findings[] = [
                'control' => 'CC6.7',
                'title' => 'Unencrypted Data Transfers',
                'description' => "Found {$unencryptedTransfers} potentially unencrypted data transfers",
                'severity' => 'high',
            ];
        }

        // CC7.2 - System monitoring
        $monitoringGaps = $this->checkMonitoringCoverage();
        if ($monitoringGaps > 0) {
            $findings[] = [
                'control' => 'CC7.2',
                'title' => 'Monitoring Coverage Gaps',
                'description' => "Found {$monitoringGaps} systems without adequate monitoring",
                'severity' => 'medium',
            ];
        }

        // CC8.1 - Change management
        $unauthorizedChanges = $this->checkUnauthorizedChanges();
        if ($unauthorizedChanges > 0) {
            $findings[] = [
                'control' => 'CC8.1',
                'title' => 'Unauthorized Configuration Changes',
                'description' => "Detected {$unauthorizedChanges} unauthorized configuration changes",
                'severity' => 'high',
            ];
        }

        return $findings;
    }

    /**
     * GDPR compliance checks.
     */
    protected function checkGdprControls(Collection $logs): array
    {
        $findings = [];

        // Article 25 - Data protection by design
        $piiExposure = $this->checkPiiExposure();
        if ($piiExposure > 0) {
            $findings[] = [
                'article' => 'Article 25',
                'title' => 'PII Exposure Risk',
                'description' => "Found {$piiExposure} potential PII exposures in logs",
                'severity' => 'high',
            ];
        }

        // Article 30 - Records of processing activities
        $processingRecords = $this->checkProcessingRecords();
        if (!$processingRecords) {
            $findings[] = [
                'article' => 'Article 30',
                'title' => 'Missing Processing Records',
                'description' => 'No record of processing activities found',
                'severity' => 'medium',
            ];
        }

        // Article 32 - Security of processing
        $encryptionCoverage = $this->checkEncryptionCoverage();
        if ($encryptionCoverage < 100) {
            $findings[] = [
                'article' => 'Article 32',
                'title' => 'Incomplete Encryption Coverage',
                'description' => "Only {$encryptionCoverage}% of sensitive data is encrypted",
                'severity' => 'high',
            ];
        }

        // Article 33 - Breach notification
        $breachDetection = $this->checkBreachDetection();
        if (!$breachDetection) {
            $findings[] = [
                'article' => 'Article 33',
                'title' => 'Breach Detection Gap',
                'description' => 'No automated breach detection mechanism found',
                'severity' => 'critical',
            ];
        }

        return $findings;
    }

    /**
     * HIPAA compliance checks.
     */
    protected function checkHipaaControls(Collection $logs): array
    {
        $findings = [];

        // 164.308 - Administrative safeguards
        $accessControl = $this->checkAccessControl();
        if (!$accessControl) {
            $findings[] = [
                'section' => '164.308',
                'title' => 'Insufficient Access Controls',
                'severity' => 'high',
            ];
        }

        // 164.312 - Technical safeguards
        $auditLogs = $this->checkAuditLogs();
        if (!$auditLogs) {
            $findings[] = [
                'section' => '164.312',
                'title' => 'Insufficient Audit Logging',
                'severity' => 'high',
            ];
        }

        // 164.314 - Organizational requirements
        $baaCoverage = $this->checkBaaCoverage();
        if (!$baaCoverage) {
            $findings[] = [
                'section' => '164.314',
                'title' => 'Missing Business Associate Agreements',
                'severity' => 'high',
            ];
        }

        return $findings;
    }

    /**
     * PCI-DSS compliance checks.
     */
    protected function checkPciDssControls(Collection $logs): array
    {
        $findings = [];

        // Requirement 1 - Firewall
        $firewallRules = $this->checkFirewallRules();
        if (!$firewallRules) {
            $findings[] = [
                'requirement' => '1.1',
                'title' => 'Firewall Configuration',
                'severity' => 'high',
            ];
        }

        // Requirement 7 - Access control
        $accessControl = $this->checkNeedToKnowAccess();
        if (!$accessControl) {
            $findings[] = [
                'requirement' => '7.1',
                'title' => 'Access Control - Need to Know',
                'severity' => 'high',
            ];
        }

        // Requirement 10 - Logging
        $auditTrail = $this->checkAuditTrailCompleteness();
        if (!$auditTrail) {
            $findings[] = [
                'requirement' => '10.2',
                'title' => 'Incomplete Audit Trail',
                'severity' => 'high',
            ];
        }

        return $findings;
    }

    /**
     * Generate recommendations based on findings.
     */
    protected function generateRecommendations(string $framework, Collection $logs): array
    {
        $recommendations = [];

        // Common recommendations
        $highRiskEvents = $logs->where('risk_score', '>=', 70)->count();
        if ($highRiskEvents > 10) {
            $recommendations[] = [
                'priority' => 'high',
                'title' => 'Implement Automated Threat Detection',
                'description' => "{$highRiskEvents} high-risk events detected. Implement automated threat detection and response.",
                'framework' => $framework,
            ];
        }

        $failedLogins = $this->getFailedLoginAttempts();
        if ($failedLogins > 50) {
            $recommendations[] = [
                'priority' => 'high',
                'title' => 'Implement Account Lockout Policy',
                'description' => "High number of failed login attempts ({$failedLogins}). Implement account lockout after 5 failed attempts.",
                'framework' => $framework,
            ];
        }

        $privilegedUsers = $this->getPrivilegedUserCount();
        if ($privilegedUsers > 10) {
            $recommendations[] = [
                'priority' => 'medium',
                'title' => 'Review Privileged Access',
                'description' => "{$privilegedUsers} users have privileged access. Review and implement least privilege principle.",
                'framework' => $framework,
            ];
        }

        return $recommendations;
    }

    /**
     * Calculate risk score for event.
     */
    protected function calculateRiskScore(array $event): int
    {
        $score = 0;

        // Base score by severity
        $severityScores = [
            'debug' => 0,
            'info' => 10,
            'warning' => 30,
            'error' => 60,
            'critical' => 90,
        ];
        $score += $severityScores[$event['severity'] ?? 'info'] ?? 10;

        // Category adjustments
        $categoryScores = [
            'security' => 30,
            'authentication' => 20,
            'data_modification' => 25,
            'data_access' => 10,
            'compliance' => 15,
            'admin' => 20,
        ];
        $score += $categoryScores[$event['category'] ?? 'general'] ?? 0;

        // Action adjustments
        $highRiskActions = ['delete', 'export', 'grant_admin', 'revoke_access', 'change_permissions'];
        if (in_array($event['action'], $highRiskActions)) {
            $score += 20;
        }

        // Privileged user
        if ($event['metadata']['is_privileged'] ?? false) {
            $score += 15;
        }

        // Failed action
        if (isset($event['metadata']['success']) && !$event['metadata']['success']) {
            $score += 15;
        }

        // Off-hours
        $hour = now()->hour;
        if ($hour < 6 || $hour > 22) {
            $score += 10;
        }

        return min(100, $score);
    }

    /**
     * Get compliance tags for event.
     */
    protected function getComplianceTags(array $event): array
    {
        $tags = [];

        // Map categories to frameworks
        $categoryMap = [
            'authentication' => ['SOC2', 'PCI-DSS'],
            'data_access' => ['GDPR', 'HIPAA', 'SOC2'],
            'data_modification' => ['GDPR', 'HIPAA', 'SOC2', 'PCI-DSS'],
            'security' => ['SOC2', 'PCI-DSS', 'HIPAA'],
            'admin' => ['SOC2', 'PCI-DSS'],
            'compliance' => ['GDPR', 'HIPAA', 'SOC2', 'PCI-DSS'],
        ];

        $tags = $categoryMap[$event['category'] ?? 'general'] ?? [];
        
        // Add specific tags based on action
        if (in_array($event['action'], ['export', 'bulk_export'])) {
            $tags = array_merge($tags, ['GDPR-Art30', 'SOC2-CC6.7']);
        }
        
        if (in_array($event['action'], ['grant_admin', 'revoke_access'])) {
            $tags = array_merge($tags, ['SOC2-CC6.1', 'PCI-7.1']);
        }

        return array_unique($tags);
    }

    /**
     * Check if event is a privileged action.
     */
    protected function isPrivilegedAction(array $event): bool
    {
        $privilegedActions = [
            'grant_admin', 'revoke_admin', 'create_user', 'delete_user',
            'change_permissions', 'modify_roles', 'access_audit_logs',
            'modify_security_settings', 'export_data', 'bulk_export',
            'modify_billing', 'cancel_subscription',
        ];

        return in_array($event['action'], $privilegedActions);
    }

    /**
     * Check if event is a data export.
     */
    protected function isDataExport(array $event): bool
    {
        return in_array($event['action'], ['export', 'bulk_export', 'download', 'generate_report']);
    }

    /**
     * Check if event is an admin action.
     */
    protected function isAdminAction(array $event): bool
    {
        $adminActions = [
            'create_user', 'delete_user', 'modify_roles', 'change_permissions',
            'modify_billing', 'access_audit_logs', 'modify_security_settings',
            'configure_integrations', 'manage_api_keys',
        ];

        return in_array($event['action'], $adminActions);
    }

    /**
     * Check for real-time alerts.
     */
    protected function checkAlerts(AuditLog $auditLog): void
    {
        // High risk score
        if ($auditLog->risk_score >= 80) {
            $this->sendAlert('high_risk_event', [
                'event_id' => $auditLog->event_id,
                'risk_score' => $auditLog->risk_score,
                'action' => $auditLog->action,
                'user_id' => $auditLog->user_id,
            ]);
        }

        // Critical severity
        if ($auditLog->severity === 'critical') {
            $this->sendAlert('critical_event', [
                'event_id' => $auditLog->event_id,
                'action' => $auditLog->action,
                'description' => $auditLog->description,
            ]);
        }

        // Multiple failed logins
        if ($auditLog->action === 'login' && ($auditLog->metadata['success'] ?? true) === false) {
            $recentFailures = AuditLog::where('user_id', $auditLog->user_id)
                ->where('action', 'login')
                ->where('metadata->success', false)
                ->where('created_at', '>=', now()->subMinutes(15))
                ->count();

            if ($recentFailures >= 5) {
                $this->sendAlert('brute_force_attempt', [
                    'user_id' => $auditLog->user_id,
                    'failed_attempts' => $recentFailures,
                ]);
            }
        }
    }

    /**
     * Send alert.
     */
    protected function sendAlert(string $type, array $data): void
    {
        foreach ($this->config['alert_channels'] as $channel) {
            try {
                match ($channel) {
                    'email' => $this->sendEmailAlert($type, $data),
                    'slack' => $this->sendSlackAlert($type, $data),
                    'webhook' => $this->sendWebhookAlert($type, $data),
                };
            } catch (\Throwable $e) {
                Log::error("Failed to send {$channel} alert", ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Alert for privileged action.
     */
    protected function alertPrivilegedAction(AuditLog $auditLog): void
    {
        $this->sendAlert('privileged_action', [
            'event_id' => $auditLog->event_id,
            'user_id' => $auditLog->user_id,
            'action' => $auditLog->action,
            'resource' => $auditLog->resource_type . ':' . $auditLog->resource_id,
        ]);
    }

    /**
     * Alert for data export.
     */
    protected function alertDataExport(AuditLog $auditLog): void
    {
        $this->sendAlert('data_export', [
            'event_id' => $auditLog->event_id,
            'user_id' => $auditLog->user_id,
            'resource' => $auditLog->resource_type . ':' . $auditLog->resource_id,
            'data_volume' => $auditLog->metadata['record_count'] ?? 'unknown',
        ]);
    }

    /**
     * Alert for admin action.
     */
    protected function alertAdminAction(AuditLog $auditLog): void
    {
        $this->sendAlert('admin_action', [
            'event_id' => $auditLog->event_id,
            'admin_id' => $auditLog->user_id,
            'action' => $auditLog->action,
            'target' => $auditLog->resource_type . ':' . $auditLog->resource_id,
        ]);
    }

    /**
     * Send email alert.
     */
    protected function sendEmailAlert(string $type, array $data): void
    {
        // Implementation would send email
        Log::info("Email alert sent: {$type}", $data);
    }

    /**
     * Send Slack alert.
     */
    protected function sendSlackAlert(string $type, array $data): void
    {
        // Implementation would send to Slack
        Log::info("Slack alert sent: {$type}", $data);
    }

    /**
     * Send webhook alert.
     */
    protected function sendWebhookAlert(string $type, array $data): void
    {
        // Implementation would call webhook
        Log::info("Webhook alert sent: {$type}", $data);
    }

    /**
     * Generate audit trail export.
     */
    public function exportAuditTrail(array $filters, string $format = 'csv'): string
    {
        $result = $this->queryLogs($filters, ['per_page' => 10000]);
        
        if ($format === 'csv') {
            return $this->exportToCsv($result['data']);
        }
        
        if ($format === 'json') {
            return json_encode($result['data'], JSON_PRETTY_PRINT);
        }

        throw new \InvalidArgumentException('Unsupported format');
    }

    /**
     * Export to CSV.
     */
    protected function exportToCsv(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $headers = array_keys($data[0]);
        $csv = implode(',', $headers) . "\n";

        foreach ($data as $row) {
            $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $row)) . "\n";
        }

        return $csv;
    }

    /**
     * Archive old logs.
     */
    public function archiveOldLogs(): int
    {
        $archiveDate = now()->subDays($this->config['archive_after_days']);
        $deleteDate = now()->subDays($this->config['retention_days']);

        // Archive to cold storage (simplified)
        $archived = AuditLog::where('created_at', '<', $archiveDate)
            ->where('archived', false)
            ->update(['archived' => true]);

        // Hard delete very old logs
        $deleted = AuditLog::where('created_at', '<', $deleteDate)
            ->forceDelete();

        Log::info('Audit log archival completed', [
            'archived' => $archived,
            'deleted' => $deleted,
        ]);

        return $archived;
    }

    /**
     * Get audit statistics.
     */
    public function getStatistics(Carbon $startDate = null, Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        $query = AuditLog::whereBetween('created_at', [$startDate, $endDate]);

        return [
            'total_events' => $query->count(),
            'by_severity' => $query->clone()->groupBy('severity')->selectRaw('severity, count(*) as count')->pluck('count', 'severity')->toArray(),
            'by_category' => $query->clone()->groupBy('category')->selectRaw('category, count(*) as count')->pluck('count', 'category')->toArray(),
            'by_action' => $query->clone()->groupBy('action')->selectRaw('action, count(*) as count')->orderBy('count', 'desc')->limit(20)->pluck('count', 'action')->toArray(),
            'unique_users' => $query->clone()->distinct('user_id')->count('user_id'),
            'high_risk_events' => $query->clone()->where('risk_score', '>=', 70)->count(),
            'failed_logins' => $query->clone()->where('action', 'login')->whereJsonContains('metadata->success', false)->count(),
            'data_exports' => $query->clone()->whereIn('action', ['export', 'bulk_export', 'download'])->count(),
            'privileged_actions' => $query->clone()->whereIn('action', ['grant_admin', 'revoke_access', 'create_user', 'delete_user'])->count(),
            'avg_risk_score' => round($query->clone()->avg('risk_score') ?? 0, 2),
            'period' => [
                'start' => $startDate->toISOString(),
                'end' => $endDate->toISOString(),
            ],
        ];
    }
}

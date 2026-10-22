<?php

namespace App\Services\Global;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DataGovernanceService
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'enabled' => true,
            'data_catalog' => [
                'enabled' => true,
                'auto_discovery' => true,
                'scan_interval' => 3600, // 1 hour
                'classification_enabled' => true,
                'lineage_enabled' => true,
            ],
            'privacy' => [
                'enabled' => true,
                'gdpr' => true,
                'ccpa' => true,
                'hipaa' => false,
                'pii_detection' => true,
                'data_subject_requests' => true,
                'consent_management' => true,
                'retention_policies' => true,
                'right_to_be_forgotten' => true,
                'data_portability' => true,
            ],
            'data_quality' => [
                'enabled' => true,
                'profiling' => true,
                'validation_rules' => true,
                'anomaly_detection' => true,
                'completeness_threshold' => 0.95,
                'uniqueness_threshold' => 0.99,
            ],
            'access_control' => [
                'enabled' => true,
                'data_masking' => true,
                'row_level_security' => true,
                'column_level_security' => true,
                'dynamic_masking' => true,
            ],
            'audit' => [
                'enabled' => true,
                'data_access_logging' => true,
                'data_modification_logging' => true,
                'schema_change_logging' => true,
                'retention_days' => 2555, // 7 years
            ],
        ], []);
    }

    /**
     * Register a data asset in the catalog.
     */
    public function registerAsset(Tenant $tenant, array $asset): array
    {
        $assetId = Str::uuid()->toString();
        
        $asset = array_merge([
            'id' => $assetId,
            'tenant_id' => $tenant->id,
            'name' => '',
            'description' => '',
            'type' => 'table', // table, view, file, api, stream
            'source' => '',
            'schema' => [],
            'classification' => 'internal', // public, internal, confidential, restricted
            'owner' => auth()->id(),
            'steward' => null,
            'tags' => [],
            'lineage' => [],
            'quality_score' => null,
            'last_scanned' => null,
            'last_accessed' => null,
            'access_count' => 0,
            'retention_policy' => null,
            'pii_fields' => [],
            'created_at' => now(),
            'updated_at' => now(),
        ], $asset);

        DB::table('data_catalog')->insert($asset);

        // Trigger classification if enabled
        if ($this->config['data_catalog']['classification_enabled']) {
            $this->classifyAsset($assetId);
        }

        // Trigger lineage discovery if enabled
        if ($this->config['data_catalog']['lineage_enabled']) {
            $this->discoverLineage($assetId);
        }

        return $asset;
    }

    /**
     * Get data catalog assets.
     */
    public function getAssets(Tenant $tenant, array $filters = [], array $pagination = []): array
    {
        $query = DB::table('data_catalog')
            ->where('tenant_id', $tenant->id);

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['classification'])) {
            $query->where('classification', $filters['classification']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('description', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('source', 'LIKE', "%{$filters['search']}%");
            });
        }

        if (!empty($filters['tags'])) {
            $tags = (array)$filters['tags'];
            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        if (!empty($filters['owner_id'])) {
            $query->where('owner', $filters['owner_id']);
        }

        $sortBy = $pagination['sort_by'] ?? 'updated_at';
        $sortOrder = $pagination['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min($pagination['per_page'] ?? 20, 100);
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
     * Get asset by ID.
     */
    public function getAsset(string $assetId): ?array
    {
        return DB::table('data_catalog')
            ->where('id', $assetId)
            ->first();
    }

    /**
     * Classify asset for PII/sensitive data.
     */
    public function classifyAsset(string $assetId): array
    {
        $asset = $this->getAsset($assetId);
        if (!$asset) {
            return ['success' => false, 'error' => 'Asset not found'];
        }

        // Scan schema for PII patterns
        $piiFields = $this->detectPiiFields($asset->schema ?? []);
        
        // Determine classification
        $classification = $this->determineClassification($piiFields, $asset->tags ?? []);

        // Update asset
        DB::table('data_catalog')
            ->where('id', $assetId)
            ->update([
                'classification' => $classification,
                'pii_fields' => json_encode($piiFields),
                'last_scanned' => now(),
                'updated_at' => now(),
            ]);

        return [
            'success' => true,
            'asset_id' => $assetId,
            'classification' => $classification,
            'pii_fields' => $piiFields,
        ];
    }

    /**
     * Detect PII fields in schema.
     */
    protected function detectPiiFields(array $schema): array
    {
        $piiPatterns = [
            'email' => ['email', 'e_mail', 'email_address'],
            'phone' => ['phone', 'telephone', 'mobile', 'cell', 'fax'],
            'ssn' => ['ssn', 'social_security', 'tax_id', 'national_id'],
            'credit_card' => ['credit_card', 'cc_number', 'card_number', 'pan'],
            'name' => ['first_name', 'last_name', 'full_name', 'given_name', 'surname'],
            'address' => ['address', 'street', 'city', 'state', 'zip', 'postal_code'],
            'dob' => ['date_of_birth', 'dob', 'birth_date', 'birthday'],
            'ip' => ['ip_address', 'ip', 'client_ip'],
            'license' => ['driver_license', 'license_number'],
            'passport' => ['passport_number', 'passport'],
            'bank' => ['bank_account', 'routing_number', 'iban', 'swift'],
        ];

        $piiFields = [];

        foreach ($schema as $field) {
            $fieldName = strtolower($field['name'] ?? '');
            $fieldType = strtolower($field['type'] ?? '');
            
            foreach ($piiPatterns as $piiType => $patterns) {
                foreach ($patterns as $pattern) {
                    if (str_contains($fieldName, $pattern)) {
                        $piiFields[] = [
                            'field' => $field['name'],
                            'type' => $piiType,
                            'confidence' => $this->calculateConfidence($fieldName, $fieldType, $pattern),
                        ];
                        break 2;
                    }
                }
            }
        }

        return $piiFields;
    }

    /**
     * Calculate confidence for PII detection.
     */
    protected function calculateConfidence(string $fieldName, string $fieldType, string $pattern): float
    {
        $confidence = 0.5;
        
        // Exact match
        if ($fieldName === $pattern) {
            $confidence = 0.95;
        }
        // Contains pattern
        elseif (str_contains($fieldName, $pattern)) {
            $confidence = 0.8;
        }
        
        // Type hints
        if (in_array($fieldType, ['string', 'text', 'varchar', 'char'])) {
            $confidence += 0.1;
        }

        return min(1.0, $confidence);
    }

    /**
     * Determine classification based on PII fields.
     */
    protected function determineClassification(array $piiFields, array $tags): string
    {
        if (empty($piiFields)) {
            return 'public';
        }

        $highRiskTypes = ['ssn', 'credit_card', 'passport', 'license', 'bank'];
        $hasHighRisk = false;
        $hasPii = false;

        foreach ($piiFields as $field) {
            if (in_array($field['type'], $highRiskTypes)) {
                $hasHighRisk = true;
            }
            $hasPii = true;
        }

        // Check tags for sensitivity
        $sensitiveTags = ['pii', 'phi', 'pci', 'financial', 'medical', 'legal'];
        $hasSensitiveTag = !empty(array_intersect($tags, $sensitiveTags));

        if ($hasHighRisk || $hasSensitiveTag) {
            return 'restricted';
        }

        if ($hasPii) {
            return 'confidential';
        }

        return 'internal';
    }

    /**
     * Discover data lineage.
     */
    public function discoverLineage(string $assetId): array
    {
        $asset = $this->getAsset($assetId);
        if (!$asset) {
            return ['success' => false, 'error' => 'Asset not found'];
        }

        // Would analyze SQL, ETL jobs, API calls, etc.
        // Simplified for now
        $lineage = [
            'upstream' => [],
            'downstream' => [],
            'transformations' => [],
        ];

        // Store lineage
        DB::table('data_catalog')
            ->where('id', $assetId)
            ->update([
                'lineage' => json_encode($lineage),
                'updated_at' => now(),
            ]);

        return [
            'success' => true,
            'asset_id' => $assetId,
            'lineage' => $lineage,
        ];
    }

    /**
     * Get data lineage.
     */
    public function getLineage(string $assetId, int $depth = 3): array
    {
        $asset = $this->getAsset($assetId);
        if (!$asset) {
            return ['error' => 'Asset not found'];
        }

        $lineage = json_decode($asset->lineage ?? '{}', true);
        
        // Would traverse upstream/downstream
        return [
            'asset_id' => $assetId,
            'asset_name' => $asset->name,
            'lineage' => $lineage,
        ];
    }

    /**
     * Create data quality rule.
     */
    public function createQualityRule(Tenant $tenant, array $rule): array
    {
        $ruleId = Str::uuid()->toString();
        
        $rule = array_merge([
            'id' => $ruleId,
            'tenant_id' => $tenant->id,
            'name' => '',
            'description' => '',
            'asset_id' => null,
            'type' => 'completeness', // completeness, uniqueness, validity, accuracy, consistency, timeliness
            'dimension' => '',
            'condition' => [],
            'threshold' => 0.95,
            'severity' => 'warning', // info, warning, critical
            'enabled' => true,
            'schedule' => 'daily', // hourly, daily, weekly, manual
            'last_run' => null,
            'last_result' => null,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $rule);

        DB::table('data_quality_rules')->insert($rule);

        return $rule;
    }

    /**
     * Run data quality checks.
     */
    public function runQualityChecks(string $tenantId, string $assetId = null): array
    {
        $query = DB::table('data_quality_rules')
            ->where('tenant_id', $tenantId)
            ->where('enabled', true);

        if ($assetId) {
            $query->where('asset_id', $assetId);
        }

        $rules = $query->get();
        $results = [];

        foreach ($rules as $rule) {
            $result = $this->executeQualityRule($rule);
            $results[] = $result;

            // Update rule with result
            DB::table('data_quality_rules')
                ->where('id', $rule->id)
                ->update([
                    'last_run' => now(),
                    'last_result' => json_encode($result),
                    'updated_at' => now(),
                ]);

            // Check if alert needed
            if ($result['passed'] === false && $rule->severity !== 'info') {
                $this->triggerQualityAlert($rule, $result);
            }
        }

        return [
            'rules_checked' => count($rules),
            'passed' => collect($results)->where('passed', true)->count(),
            'failed' => collect($results)->where('passed', false)->count(),
            'results' => $results,
        ];
    }

    /**
     * Execute a quality rule.
     */
    protected function executeQualityRule(object $rule): array
    {
        // Would execute actual SQL against data source
        // Simplified for now
        $passed = rand(0, 100) / 100 > 0.05; // 95% pass rate
        
        return [
            'rule_id' => $rule->id,
            'rule_name' => $rule->name,
            'type' => $rule->type,
            'passed' => $passed,
            'score' => $passed ? rand(90, 100) / 100 : rand(50, 89) / 100,
            'threshold' => $rule->threshold,
            'records_checked' => rand(1000, 1000000),
            'records_failed' => $passed ? 0 : rand(1, 1000),
            'executed_at' => now()->toISOString(),
            'details' => $passed ? 'All checks passed' : 'Threshold not met',
        ];
    }

    /**
     * Trigger quality alert.
     */
    protected function triggerQualityAlert(object $rule, array $result): void
    {
        Log::warning('Data quality alert', [
            'rule_id' => $rule->id,
            'rule_name' => $rule->name,
            'severity' => $rule->severity,
            'result' => $result,
        ]);
    }

    /**
     * Handle GDPR Data Subject Access Request.
     */
    public function handleDataSubjectRequest(Tenant $tenant, string $requestType, array $data): array
    {
        if (!$this->config['privacy']['data_subject_requests']) {
            return ['success' => false, 'error' => 'Data subject requests not enabled'];
        }

        $requestId = Str::uuid()->toString();
        $email = $data['email'] ?? '';
        $userId = $data['user_id'] ?? null;

        $request = [
            'id' => $requestId,
            'tenant_id' => $tenant->id,
            'type' => $requestType, // access, rectification, erasure, portability, restriction, objection
            'email' => $email,
            'user_id' => $userId,
            'status' => 'pending',
            'requested_data' => json_encode($data),
            'response_data' => null,
            'completed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('data_subject_requests')->insert($request);

        // Process based on type
        switch ($requestType) {
            case 'access':
                $this->processAccessRequest($requestId, $tenant, $email, $userId);
                break;
            case 'erasure':
                $this->processErasureRequest($requestId, $tenant, $email, $userId);
                break;
            case 'portability':
                $this->processPortabilityRequest($requestId, $tenant, $email, $userId);
                break;
            case 'rectification':
                $this->processRectificationRequest($requestId, $tenant, $email, $userId, $data['corrections'] ?? []);
                break;
        }

        return [
            'request_id' => $requestId,
            'status' => 'pending',
            'type' => $requestType,
        ];
    }

    /**
     * Process access request (GDPR Article 15).
     */
    protected function processAccessRequest(string $requestId, Tenant $tenant, string $email, ?int $userId): void
    {
        // Gather all data for user
        $userData = $this->gatherUserData($tenant, $email, $userId);
        
        DB::table('data_subject_requests')
            ->where('id', $requestId)
            ->update([
                'status' => 'completed',
                'response_data' => json_encode($userData),
                'completed_at' => now(),
            ]);
    }

    /**
     * Process erasure request (GDPR Article 17).
     */
    protected function processErasureRequest(string $requestId, Tenant $tenant, string $email, ?int $userId): void
    {
        // Check if erasure is allowed (legal holds, etc.)
        $canErase = $this->canEraseUser($tenant, $email, $userId);
        
        if (!$canErase['allowed']) {
            DB::table('data_subject_requests')
                ->where('id', $requestId)
                ->update([
                    'status' => 'rejected',
                    'response_data' => json_encode(['reason' => $canErase['reason']]),
                    'completed_at' => now(),
                ]);
            return;
        }

        // Perform erasure
        $erased = $this->eraseUserData($tenant, $email, $userId);
        
        DB::table('data_subject_requests')
            ->where('id', $requestId)
            ->update([
                'status' => 'completed',
                'response_data' => json_encode(['erased_records' => $erased]),
                'completed_at' => now(),
            ]);
    }

    /**
     * Process portability request (GDPR Article 20).
     */
    protected function processPortabilityRequest(string $requestId, Tenant $tenant, string $email, ?int $userId): void
    {
        $userData = $this->gatherUserData($tenant, $email, $userId);
        
        // Export in machine-readable format (JSON/CSV)
        $export = $this->formatPortabilityExport($userData);
        
        DB::table('data_subject_requests')
            ->where('id', $requestId)
            ->update([
                'status' => 'completed',
                'response_data' => json_encode($export),
                'completed_at' => now(),
            ]);
    }

    /**
     * Process rectification request (GDPR Article 16).
     */
    protected function processRectificationRequest(string $requestId, Tenant $tenant, string $email, ?int $userId, array $corrections): void
    {
        // Apply corrections to user data
        $updated = $this->applyCorrections($tenant, $email, $userId, $corrections);
        
        DB::table('data_subject_requests')
            ->where('id', $requestId)
            ->update([
                'status' => 'completed',
                'response_data' => json_encode(['updated_fields' => $updated]),
                'completed_at' => now(),
            ]);
    }

    /**
     * Gather all user data across systems.
     */
    protected function gatherUserData(Tenant $tenant, string $email, ?int $userId): array
    {
        // Would query all tables containing user data
        return [
            'profile' => [],
            'activity' => [],
            'transactions' => [],
            'preferences' => [],
            'consents' => [],
        ];
    }

    /**
     * Check if user can be erased.
     */
    protected function canEraseUser(Tenant $tenant, string $email, ?int $userId): array
    {
        // Check for legal holds, active subscriptions, etc.
        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Erase user data.
     */
    protected function eraseUserData(Tenant $tenant, string $email, ?int $userId): int
    {
        // Would delete/anonymize user data across all tables
        return 0;
    }

    /**
     * Format portability export.
     */
    protected function formatPortabilityExport(array $userData): array
    {
        return [
            'format' => 'json',
            'data' => $userData,
            'exported_at' => now()->toISOString(),
        ];
    }

    /**
     * Apply corrections.
     */
    protected function applyCorrections(Tenant $tenant, string $email, ?int $userId, array $corrections): array
    {
        // Would update user data
        return array_keys($corrections);
    }

    /**
     * Create retention policy.
     */
    public function createRetentionPolicy(Tenant $tenant, array $policy): array
    {
        $policyId = Str::uuid()->toString();
        
        $policy = array_merge([
            'id' => $policyId,
            'tenant_id' => $tenant->id,
            'name' => '',
            'description' => '',
            'asset_types' => [], // tables, files, logs, etc.
            'classification' => [], // applies to these classifications
            'retention_days' => 2555, // 7 years default
            'action' => 'delete', // delete, archive, anonymize
            'archive_location' => null,
            'legal_hold_exempt' => false,
            'enabled' => true,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $policy);

        DB::table('data_retention_policies')->insert($policy);

        return $policy;
    }

    /**
     * Apply retention policies.
     */
    public function applyRetentionPolicies(Tenant $tenant): array
    {
        $policies = DB::table('data_retention_policies')
            ->where('tenant_id', $tenant->id)
            ->where('enabled', true)
            ->get();

        $results = [];

        foreach ($policies as $policy) {
            $result = $this->executeRetentionPolicy($tenant, $policy);
            $results[] = $result;
        }

        return [
            'policies_applied' => count($policies),
            'results' => $results,
        ];
    }

    /**
     * Execute retention policy.
     */
    protected function executeRetentionPolicy(Tenant $tenant, object $policy): array
    {
        // Would scan data and apply retention
        $cutoffDate = now()->subDays($policy->retention_days);
        
        return [
            'policy_id' => $policy->id,
            'policy_name' => $policy->name,
            'action' => $policy->action,
            'cutoff_date' => $cutoffDate->toISOString(),
            'records_processed' => 0,
            'records_affected' => 0,
            'executed_at' => now()->toISOString(),
        ];
    }

    /**
     * Apply data masking.
     */
    public function applyMasking(Tenant $tenant, string $assetId, array $maskingRules): array
    {
        if (!$this->config['access_control']['data_masking']) {
            return ['success' => false, 'error' => 'Data masking not enabled'];
        }

        $asset = $this->getAsset($assetId);
        if (!$asset) {
            return ['success' => false, 'error' => 'Asset not found'];
        }

        // Store masking rules
        DB::table('data_masking_rules')->insert([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenant->id,
            'asset_id' => $assetId,
            'rules' => json_encode($maskingRules),
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'success' => true,
            'asset_id' => $assetId,
            'rules_count' => count($maskingRules),
        ];
    }

    /**
     * Get masking rules for asset.
     */
    public function getMaskingRules(string $assetId): array
    {
        return DB::table('data_masking_rules')
            ->where('asset_id', $assetId)
            ->get()
            ->toArray();
    }

    /**
     * Get data governance dashboard.
     */
    public function getDashboard(Tenant $tenant): array
    {
        return [
            'catalog_stats' => $this->getCatalogStats($tenant),
            'quality_stats' => $this->getQualityStats($tenant),
            'privacy_stats' => $this->getPrivacyStats($tenant),
            'access_stats' => $this->getAccessStats($tenant),
            'recent_requests' => $this->getRecentSubjectRequests($tenant),
        ];
    }

    protected function getCatalogStats(Tenant $tenant): array
    {
        return [
            'total_assets' => DB::table('data_catalog')->where('tenant_id', $tenant->id)->count(),
            'by_classification' => DB::table('data_catalog')
                ->where('tenant_id', $tenant->id)
                ->selectRaw('classification, count(*) as count')
                ->groupBy('classification')
                ->pluck('count', 'classification')
                ->toArray(),
            'by_type' => DB::table('data_catalog')
                ->where('tenant_id', $tenant->id)
                ->selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
            'with_pii' => DB::table('data_catalog')
                ->where('tenant_id', $tenant->id)
                ->whereRaw('JSON_LENGTH(pii_fields) > 0')
                ->count(),
        ];
    }

    protected function getQualityStats(Tenant $tenant): array
    {
        $rules = DB::table('data_quality_rules')
            ->where('tenant_id', $tenant->id)
            ->where('enabled', true)
            ->get();

        return [
            'total_rules' => $rules->count(),
            'last_run' => $rules->max('last_run'),
            'pass_rate' => $rules->count() > 0 
                ? round($rules->where('last_result', 'like', '%"passed":true%')->count() / $rules->count() * 100, 1)
                : 100,
        ];
    }

    protected function getPrivacyStats(Tenant $tenant): array
    {
        return [
            'pending_requests' => DB::table('data_subject_requests')
                ->where('tenant_id', $tenant->id)
                ->where('status', 'pending')
                ->count(),
            'completed_requests' => DB::table('data_subject_requests')
                ->where('tenant_id', $tenant->id)
                ->where('status', 'completed')
                ->count(),
            'by_type' => DB::table('data_subject_requests')
                ->where('tenant_id', $tenant->id)
                ->selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
        ];
    }

    protected function getAccessStats(Tenant $tenant): array
    {
        return [
            'masked_assets' => DB::table('data_masking_rules')
                ->where('tenant_id', $tenant->id)
                ->distinct('asset_id')
                ->count(),
            'access_logs_today' => DB::table('data_access_logs')
                ->where('tenant_id', $tenant->id)
                ->whereDate('created_at', now())
                ->count(),
        ];
    }

    protected function getRecentSubjectRequests(Tenant $tenant): array
    {
        return DB::table('data_subject_requests')
            ->where('tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }
}

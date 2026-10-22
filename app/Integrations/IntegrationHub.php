<?php

namespace App\Integrations;

use App\Models\Tenant;
use App\Models\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class IntegrationHub
{
    protected Tenant $tenant;
    protected ?Client $client;
    protected array $connections = [];

    public function __construct(Tenant $tenant, ?Client $client = null)
    {
        $this->tenant = $tenant;
        $this->client = $client;
    }

    /**
     * Get a platform connection.
     */
    public function getConnection(string $platform): ?PlatformConnection
    {
        if (isset($this->connections[$platform])) {
            return $this->connections[$platform];
        }

        $connection = $this->loadConnection($platform);
        if ($connection) {
            $this->connections[$platform] = $connection;
        }

        return $connection;
    }

    /**
     * Load connection from database.
     */
    protected function loadConnection(string $platform): ?PlatformConnection
    {
        if ($this->client) {
            // Check client-specific connection first
            $clientConnections = $this->client->ad_accounts ?? [];
            if (isset($clientConnections[$platform])) {
                return new PlatformConnection($platform, $clientConnections[$platform]);
            }
        }

        // Fall back to tenant-level connection
        $tenantConnections = $this->tenant->integrations ?? [];
        if (isset($tenantConnections[$platform])) {
            return new PlatformConnection($platform, $tenantConnections[$platform]);
        }

        return null;
    }

    /**
     * Sync all platforms for a client.
     */
    public function syncAll(?Client $client = null): array
    {
        $client = $client ?? $this->client;
        if (!$client) {
            throw new \InvalidArgumentException('Client required for sync');
        }

        $results = [];
        $platforms = ['meta', 'google', 'linkedin', 'tiktok'];

        foreach ($platforms as $platform) {
            $connection = $this->getConnection($platform);
            if ($connection) {
                try {
                    $result = $connection->sync($client);
                    $results[$platform] = ['success' => true, 'data' => $result];
                } catch (\Throwable $e) {
                    $results[$platform] = ['success' => false, 'error' => $e->getMessage()];
                    Log::error("Platform sync failed", [
                        'platform' => $platform,
                        'client_id' => $client->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                $results[$platform] = ['success' => false, 'error' => 'No connection configured'];
            }
        }

        return $results;
    }

    /**
     * Get unified campaign data across platforms.
     */
    public function getUnifiedCampaigns(Client $client, array $filters = []): array
    {
        $allCampaigns = [];

        foreach (['meta', 'google', 'linkedin', 'tiktok'] as $platform) {
            $connection = $this->getConnection($platform);
            if ($connection) {
                try {
                    $campaigns = $connection->getCampaigns($filters);
                    foreach ($campaigns as $campaign) {
                        $campaign['platform'] = $platform;
                        $allCampaigns[] = $this->normalizeCampaign($campaign, $platform);
                    }
                } catch (\Throwable $e) {
                    Log::error("Failed to get campaigns from {$platform}", [
                        'client_id' => $client->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Sort by spend descending
        usort($allCampaigns, fn($a, $b) => ($b['spend'] ?? 0) <=> ($a['spend'] ?? 0));

        return $allCampaigns;
    }

    /**
     * Get unified performance metrics.
     */
    public function getUnifiedMetrics(Client $client, string $dateRange = 'last_30_days'): array
    {
        $metrics = [
            'total_spend' => 0,
            'total_impressions' => 0,
            'total_clicks' => 0,
            'total_conversions' => 0,
            'weighted_ctr' => 0,
            'weighted_cpc' => 0,
            'weighted_cpa' => 0,
            'weighted_roas' => 0,
            'by_platform' => [],
        ];

        foreach (['meta', 'google', 'linkedin', 'tiktok'] as $platform) {
            $connection = $this->getConnection($platform);
            if ($connection) {
                try {
                    $platformMetrics = $connection->getMetrics($dateRange);
                    $platformMetrics['platform'] = $platform;
                    $metrics['by_platform'][$platform] = $platformMetrics;

                    // Aggregate
                    $metrics['total_spend'] += $platformMetrics['spend'] ?? 0;
                    $metrics['total_impressions'] += $platformMetrics['impressions'] ?? 0;
                    $metrics['total_clicks'] += $platformMetrics['clicks'] ?? 0;
                    $metrics['total_conversions'] += $platformMetrics['conversions'] ?? 0;

                } catch (\Throwable $e) {
                    Log::error("Failed to get metrics from {$platform}", [
                        'client_id' => $client->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Calculate weighted averages
        if ($metrics['total_impressions'] > 0) {
            $metrics['weighted_ctr'] = ($metrics['total_clicks'] / $metrics['total_impressions']) * 100;
        }
        if ($metrics['total_clicks'] > 0) {
            $metrics['weighted_cpc'] = $metrics['total_spend'] / $metrics['total_clicks'];
        }
        if ($metrics['total_conversions'] > 0) {
            $metrics['weighted_cpa'] = $metrics['total_spend'] / $metrics['total_conversions'];
        }
        if ($metrics['total_spend'] > 0) {
            $totalRevenue = 0;
            foreach ($metrics['by_platform'] as $p) {
                $totalRevenue += ($p['roas'] ?? 0) * ($p['spend'] ?? 0);
            }
            $metrics['weighted_roas'] = $totalRevenue / $metrics['total_spend'];
        }

        return $metrics;
    }

    /**
     * Normalize campaign data across platforms.
     */
    protected function normalizeCampaign(array $campaign, string $platform): array
    {
        return [
            'id' => $campaign['id'] ?? '',
            'platform_id' => $campaign['id'] ?? '',
            'platform' => $platform,
            'name' => $campaign['name'] ?? '',
            'status' => $this->normalizeStatus($campaign['status'] ?? '', $platform),
            'objective' => $campaign['objective'] ?? '',
            'budget' => $campaign['budget'] ?? $campaign['daily_budget'] ?? 0,
            'spend' => $campaign['spend'] ?? $campaign['amount_spent'] ?? 0,
            'impressions' => $campaign['impressions'] ?? 0,
            'clicks' => $campaign['clicks'] ?? 0,
            'conversions' => $campaign['conversions'] ?? $campaign['conversion_values'] ?? 0,
            'ctr' => $campaign['ctr'] ?? 0,
            'cpc' => $campaign['cpc'] ?? 0,
            'cpa' => $campaign['cpa'] ?? 0,
            'roas' => $campaign['roas'] ?? 0,
            'start_date' => $campaign['start_date'] ?? $campaign['start_time'] ?? null,
            'end_date' => $campaign['end_date'] ?? $campaign['end_time'] ?? null,
            'raw_data' => $campaign,
        ];
    }

    /**
     * Normalize status across platforms.
     */
    protected function normalizeStatus(string $status, string $platform): string
    {
        $statusMap = [
            'meta' => [
                'ACTIVE' => 'active',
                'PAUSED' => 'paused',
                'DELETED' => 'deleted',
                'ARCHIVED' => 'archived',
            ],
            'google' => [
                'ENABLED' => 'active',
                'PAUSED' => 'paused',
                'REMOVED' => 'deleted',
            ],
            'linkedin' => [
                'ACTIVE' => 'active',
                'PAUSED' => 'paused',
                'ARCHIVED' => 'archived',
            ],
            'tiktok' => [
                'ENABLE' => 'active',
                'DISABLE' => 'paused',
                'DELETE' => 'deleted',
            ],
        ];

        return $statusMap[$platform][$status] ?? strtolower($status);
    }
}

/**
 * Base platform connection class.
 */
abstract class PlatformConnection
{
    protected string $platform;
    protected array $config;
    protected string $accessToken;

    public function __construct(string $platform, array $config)
    {
        $this->platform = $platform;
        $this->config = $config;
        $this->accessToken = $config['access_token'] ?? '';
    }

    abstract public function sync(Client $client): array;
    abstract public function getCampaigns(array $filters = []): array;
    abstract public function getMetrics(string $dateRange = 'last_30_days'): array;
    abstract public function getCreatives(string $campaignId): array;
    abstract public function createCampaign(array $data): array;
    abstract public function updateCampaign(string $campaignId, array $data): array;

    /**
     * Make authenticated API request.
     */
    protected function request(string $method, string $endpoint, array $params = []): array
    {
        $url = $this->getBaseUrl() . $endpoint;
        
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->accessToken}",
            'Content-Type' => 'application/json',
        ])->timeout(60)->$method($url, $params);

        if (!$response->successful()) {
            throw new \Exception("API request failed: {$response->status()} - {$response->body()}");
        }

        return $response->json();
    }

    abstract protected function getBaseUrl(): string;
}

/**
 * Meta (Facebook/Instagram) Ads API Connection.
 */
class MetaConnection extends PlatformConnection
{
    protected function getBaseUrl(): string
    {
        return 'https://graph.facebook.com/v18.0/';
    }

    public function sync(Client $client): array
    {
        // Sync ad accounts, campaigns, ad sets, ads
        $adAccounts = $this->getAdAccounts();
        $synced = 0;

        foreach ($adAccounts as $account) {
            $campaigns = $this->getCampaigns(['account_id' => $account['id']]);
            foreach ($campaigns as $campaign) {
                $this->upsertCampaign($client, $campaign);
                $synced++;
            }
        }

        return ['synced' => $synced, 'accounts' => count($adAccounts)];
    }

    public function getCampaigns(array $filters = []): array
    {
        $accountId = $filters['account_id'] ?? ($this->config['ad_account_id'] ?? '');
        if (!$accountId) {
            throw new \Exception('Ad account ID required for Meta campaigns');
        }

        $fields = 'id,name,status,objective,daily_budget,lifetime_budget,start_time,stop_time,objective';
        $params = [
            'fields' => $fields,
            'limit' => 100,
        ];

        if (!empty($filters['status'])) {
            $params['filtering'] = json_encode([[
                'field' => 'status',
                'operator' => 'IN',
                'value' => array_map('strtoupper', (array)$filters['status']),
            ]]);
        }

        $response = $this->request('GET', "act_{$accountId}/campaigns", $params);
        return $response['data'] ?? [];
    }

    public function getMetrics(string $dateRange = 'last_30_days'): array
    {
        $accountId = $this->config['ad_account_id'] ?? '';
        if (!$accountId) {
            throw new \Exception('Ad account ID required for Meta metrics');
        }

        $datePreset = $this->getDatePreset($dateRange);
        
        $response = $this->request('GET', "act_{$accountId}/insights", [
            'fields' => 'spend,impressions,clicks,conversions,ctr,cpc,cpa,roas',
            'date_preset' => $datePreset,
            'level' => 'account',
        ]);

        $data = $response['data'][0] ?? [];
        return [
            'spend' => (float)($data['spend'] ?? 0),
            'impressions' => (int)($data['impressions'] ?? 0),
            'clicks' => (int)($data['clicks'] ?? 0),
            'conversions' => (float)($data['conversions'] ?? 0),
            'ctr' => (float)($data['ctr'] ?? 0),
            'cpc' => (float)($data['cpc'] ?? 0),
            'cpa' => (float)($data['cpa'] ?? 0),
            'roas' => (float)($data['roas'] ?? 0),
        ];
    }

    public function getCreatives(string $campaignId): array
    {
        // Implementation for getting ad creatives
        return [];
    }

    public function createCampaign(array $data): array
    {
        $accountId = $this->config['ad_account_id'] ?? '';
        return $this->request('POST', "act_{$accountId}/campaigns", $data);
    }

    public function updateCampaign(string $campaignId, array $data): array
    {
        return $this->request('POST', $campaignId, $data);
    }

    protected function getAdAccounts(): array
    {
        $response = $this->request('GET', 'me/adaccounts', ['fields' => 'id,name,currency,timezone_name']);
        return $response['data'] ?? [];
    }

    protected function getDatePreset(string $range): string
    {
        return match ($range) {
            'last_7_days' => 'last_7_days',
            'last_30_days' => 'last_30_days',
            'last_90_days' => 'last_90_days',
            'this_month' => 'this_month',
            'last_month' => 'last_month',
            default => 'last_30_days',
        };
    }

    protected function upsertCampaign(Client $client, array $campaign): void
    {
        // Implementation for upserting campaign to local DB
    }
}

/**
 * Google Ads API Connection.
 */
class GoogleConnection extends PlatformConnection
{
    protected function getBaseUrl(): string
    {
        return 'https://googleads.googleapis.com/v15/';
    }

    public function sync(Client $client): array
    {
        // Sync Google Ads campaigns
        return ['synced' => 0, 'message' => 'Google Ads sync not fully implemented'];
    }

    public function getCampaigns(array $filters = []): array
    {
        // Implementation for Google Ads campaigns
        return [];
    }

    public function getMetrics(string $dateRange = 'last_30_days'): array
    {
        return [];
    }

    public function getCreatives(string $campaignId): array
    {
        return [];
    }

    public function createCampaign(array $data): array
    {
        return [];
    }

    public function updateCampaign(string $campaignId, array $data): array
    {
        return [];
    }

    protected function getBaseUrl(): string
    {
        return 'https://googleads.googleapis.com/v15/';
    }
}

/**
 * LinkedIn Ads API Connection.
 */
class LinkedInConnection extends PlatformConnection
{
    protected function getBaseUrl(): string
    {
        return 'https://api.linkedin.com/v2/';
    }

    public function sync(Client $client): array
    {
        return ['synced' => 0, 'message' => 'LinkedIn sync not fully implemented'];
    }

    public function getCampaigns(array $filters = []): array
    {
        return [];
    }

    public function getMetrics(string $dateRange = 'last_30_days'): array
    {
        return [];
    }

    public function getCreatives(string $campaignId): array
    {
        return [];
    }

    public function createCampaign(array $data): array
    {
        return [];
    }

    public function updateCampaign(string $campaignId, array $data): array
    {
        return [];
    }

    protected function getBaseUrl(): string
    {
        return 'https://api.linkedin.com/v2/';
    }
}

/**
 * TikTok Ads API Connection.
 */
class TikTokConnection extends PlatformConnection
{
    protected function getBaseUrl(): string
    {
        return 'https://business-api.tiktok.com/open_api/v1.3/';
    }

    public function sync(Client $client): array
    {
        return ['synced' => 0, 'message' => 'TikTok sync not fully implemented'];
    }

    public function getCampaigns(array $filters = []): array
    {
        return [];
    }

    public function getMetrics(string $dateRange = 'last_30_days'): array
    {
        return [];
    }

    public function getCreatives(string $campaignId): array
    {
        return [];
    }

    public function createCampaign(array $data): array
    {
        return [];
    }

    public function updateCampaign(string $campaignId, array $data): array
    {
        return [];
    }

    protected function getBaseUrl(): string
    {
        return 'https://business-api.tiktok.com/open_api/v1.3/';
    }
}

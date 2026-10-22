<?php

namespace App\Integrations\Contracts;

use App\Domain\Campaign\DTO\CampaignData;
use App\Domain\Campaign\DTO\AdSetData;
use App\Domain\Campaign\DTO\CreativeData;
use App\Domain\Analytics\DTO\MetricsData;
use Illuminate\Support\Collection;

interface AdPlatformInterface
{
    /**
     * Platform identifier (meta, google, linkedin, tiktok)
     */
    public function getPlatformId(): string;

    /**
     * Platform display name
     */
    public function getPlatformName(): string;

    /**
     * Authenticate and validate credentials
     */
    public function authenticate(array $credentials): bool;

    /**
     * Get ad accounts accessible with credentials
     */
    public function getAdAccounts(array $credentials): Collection;

    /**
     * Campaign Management
     */
    public function createCampaign(array $credentials, CampaignData $campaign): array;
    public function updateCampaign(array $credentials, string $campaignId, CampaignData $campaign): array;
    public function deleteCampaign(array $credentials, string $campaignId): bool;
    public function getCampaign(array $credentials, string $campaignId): ?CampaignData;
    public function listCampaigns(array $credentials, array $filters = []): Collection;

    /**
     * Ad Set / Ad Group Management
     */
    public function createAdSet(array $credentials, AdSetData $adSet): array;
    public function updateAdSet(array $credentials, string $adSetId, AdSetData $adSet): array;
    public function getAdSet(array $credentials, string $adSetId): ?AdSetData;
    public function listAdSets(array $credentials, string $campaignId, array $filters = []): Collection;

    /**
     * Creative / Ad Management
     */
    public function createCreative(array $credentials, CreativeData $creative): array;
    public function updateCreative(array $credentials, string $creativeId, CreativeData $creative): array;
    public function getCreative(array $credentials, string $creativeId): ?CreativeData;
    public function listCreatives(array $credentials, array $filters = []): Collection;

    /**
     * Metrics & Reporting
     */
    public function getMetrics(array $credentials, string $objectId, string $objectType, array $params = []): MetricsData;
    public function getMetricsBreakdown(array $credentials, string $objectId, string $objectType, string $breakdown, array $params = []): Collection;
    public function getRealtimeMetrics(array $credentials, string $objectId, string $objectType): MetricsData;

    /**
     * Audience & Targeting
     */
    public function createAudience(array $credentials, array $audienceData): array;
    public function getAudience(array $credentials, string $audienceId): ?array;
    public function listAudiences(array $credentials): Collection;
    public function getAudienceInsights(array $credentials, string $audienceId): array;

    /**
     * Budget & Bidding
     */
    public function updateBudget(array $credentials, string $objectId, string $objectType, float $budget, string $budgetType): bool;
    public function updateBidStrategy(array $credentials, string $objectId, string $objectType, array $bidConfig): bool;

    /**
     * Webhook handling
     */
    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool;
    public function parseWebhookPayload(string $payload): array;

    /**
     * Rate limit info
     */
    public function getRateLimitInfo(): array;

    /**
     * Health check
     */
    public function healthCheck(array $credentials): bool;
}

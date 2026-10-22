<?php

namespace App\Domain\Analytics\DTO;

use Carbon\Carbon;
use Illuminate\Support\Collection;

readonly class MetricsData
{
    public function __construct(
        public string $objectId,
        public string $objectType, // campaign, ad_set, creative, account
        public Carbon $dateStart,
        public Carbon $dateEnd,
        public float $spend = 0,
        public int $impressions = 0,
        public int $clicks = 0,
        public int $conversions = 0,
        public float $ctr = 0,
        public float $cpc = 0,
        public float $cpm = 0,
        public float $cpa = 0,
        public float $roas = 0,
        public float $conversionRate = 0,
        public float $frequency = 0,
        public float $reach = 0,
        public float $videoViews = 0,
        public float $videoPlayRate = 0,
        public float $costPerVideoView = 0,
        public int $linkClicks = 0,
        public float $costPerLinkClick = 0,
        public int $landingPageViews = 0,
        public float $costPerLandingPageView = 0,
        public Collection $breakdowns = null, // age, gender, placement, device, region
        public Collection $timeSeries = null, // daily/hourly breakdown
        public array $customMetrics = [], // platform-specific metrics
        public ?Carbon $fetchedAt = null,
    ) {}

    public static function empty(string $objectId, string $objectType, Carbon $dateStart, Carbon $dateEnd): self
    {
        return new self(
            objectId: $objectId,
            objectType: $objectType,
            dateStart: $dateStart,
            dateEnd: $dateEnd,
            fetchedAt: now(),
        );
    }

    public function calculateDerivedMetrics(): self
    {
        $ctr = $this->impressions > 0 ? ($this->clicks / $this->impressions) * 100 : 0;
        $cpc = $this->clicks > 0 ? $this->spend / $this->clicks : 0;
        $cpm = $this->impressions > 0 ? ($this->spend / $this->impressions) * 1000 : 0;
        $cpa = $this->conversions > 0 ? $this->spend / $this->conversions : 0;
        $roas = $this->spend > 0 ? ($this->conversions * 100) / $this->spend : 0; // Assuming $100 per conversion
        $conversionRate = $this->clicks > 0 ? ($this->conversions / $this->clicks) * 100 : 0;
        $frequency = $this->reach > 0 ? $this->impressions / $this->reach : 0;
        $videoPlayRate = $this->impressions > 0 ? ($this->videoViews / $this->impressions) * 100 : 0;
        $costPerVideoView = $this->videoViews > 0 ? $this->spend / $this->videoViews : 0;
        $costPerLinkClick = $this->linkClicks > 0 ? $this->spend / $this->linkClicks : 0;
        $costPerLandingPageView = $this->landingPageViews > 0 ? $this->spend / $this->landingPageViews : 0;

        return new self(
            objectId: $this->objectId,
            objectType: $this->objectType,
            dateStart: $this->dateStart,
            dateEnd: $this->dateEnd,
            spend: $this->spend,
            impressions: $this->impressions,
            clicks: $this->clicks,
            conversions: $this->conversions,
            ctr: round($ctr, 2),
            cpc: round($cpc, 2),
            cpm: round($cpm, 2),
            cpa: round($cpa, 2),
            roas: round($roas, 2),
            conversionRate: round($conversionRate, 2),
            frequency: round($frequency, 2),
            reach: $this->reach,
            videoViews: $this->videoViews,
            videoPlayRate: round($videoPlayRate, 2),
            costPerVideoView: round($costPerVideoView, 2),
            linkClicks: $this->linkClicks,
            costPerLinkClick: round($costPerLinkClick, 2),
            landingPageViews: $this->landingPageViews,
            costPerLandingPageView: round($costPerLandingPageView, 2),
            breakdowns: $this->breakdowns,
            timeSeries: $this->timeSeries,
            customMetrics: $this->customMetrics,
            fetchedAt: now(),
        );
    }

    public function toArray(): array
    {
        return [
            'object_id' => $this->objectId,
            'object_type' => $this->objectType,
            'date_start' => $this->dateStart->toISOString(),
            'date_end' => $this->dateEnd->toISOString(),
            'spend' => $this->spend,
            'impressions' => $this->impressions,
            'clicks' => $this->clicks,
            'conversions' => $this->conversions,
            'ctr' => $this->ctr,
            'cpc' => $this->cpc,
            'cpm' => $this->cpm,
            'cpa' => $this->cpa,
            'roas' => $this->roas,
            'conversion_rate' => $this->conversionRate,
            'frequency' => $this->frequency,
            'reach' => $this->reach,
            'video_views' => $this->videoViews,
            'video_play_rate' => $this->videoPlayRate,
            'cost_per_video_view' => $this->costPerVideoView,
            'link_clicks' => $this->linkClicks,
            'cost_per_link_click' => $this->costPerLinkClick,
            'landing_page_views' => $this->landingPageViews,
            'cost_per_landing_page_view' => $this->costPerLandingPageView,
            'breakdowns' => $this->breakdowns?->toArray(),
            'time_series' => $this->timeSeries?->toArray(),
            'custom_metrics' => $this->customMetrics,
            'fetched_at' => $this->fetchedAt?->toISOString(),
        ];
    }

    public function getPerformanceScore(): float
    {
        // Composite score: ROAS weight 40%, CTR weight 20%, Conversion Rate weight 20%, Cost efficiency weight 20%
        $roasScore = min($this->roas / 4, 1) * 40; // Target 4x ROAS
        $ctrScore = min($this->ctr / 3, 1) * 20; // Target 3% CTR
        $convScore = min($this->conversionRate / 5, 1) * 20; // Target 5% conversion rate
        $costScore = $this->cpa > 0 ? max(0, (1 - $this->cpa / 100)) * 20 : 20; // Target < $100 CPA

        return round($roasScore + $ctrScore + $convScore + $costScore, 2);
    }
}

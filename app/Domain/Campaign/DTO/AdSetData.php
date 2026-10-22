<?php

namespace App\Domain\Campaign\DTO;

use Carbon\Carbon;
use Illuminate\Support\Collection;

readonly class AdSetData
{
    public function __construct(
        public string $name,
        public string $campaignId,
        public string $optimizationGoal, // conversions, link_clicks, landing_page_views, etc.
        public string $billingEvent, // impressions, link_clicks, etc.
        public ?string $id = null,
        public ?float $budget = null,
        public ?string $budgetType = 'daily',
        public ?Carbon $startDate = null,
        public ?Carbon $endDate = null,
        public ?array $targeting = null, // demographics, interests, behaviors, custom audiences
        public ?array $placement = null, // feeds, stories, reels, search, etc.
        public ?array $creativeIds = null,
        public ?array $bidStrategy = null, // lowest_cost, target_cpa, target_roas, cost_cap
        public ?float $bidAmount = null,
        public ?array $metadata = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'campaign_id' => $this->campaignId,
            'optimization_goal' => $this->optimizationGoal,
            'billing_event' => $this->billingEvent,
            'budget' => $this->budget,
            'budget_type' => $this->budgetType,
            'start_date' => $this->startDate?->toISOString(),
            'end_date' => $this->endDate?->toISOString(),
            'targeting' => $this->targeting,
            'placement' => $this->placement,
            'creative_ids' => $this->creativeIds,
            'bid_strategy' => $this->bidStrategy,
            'bid_amount' => $this->bidAmount,
            'metadata' => $this->metadata,
        ];
    }
}

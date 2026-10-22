<?php

namespace App\Domain\Campaign\DTO;

use Carbon\Carbon;
use Illuminate\Support\Collection;

readonly class CampaignData
{
    public function __construct(
        public string $name,
        public string $objective, // conversions, traffic, awareness, etc.
        public string $status, // draft, active, paused, archived
        public ?string $id = null,
        public ?string $clientId = null,
        public ?float $budget = null,
        public ?string $budgetType = 'daily', // daily, lifetime
        public ?Carbon $startDate = null,
        public ?Carbon $endDate = null,
        public ?array $targeting = null,
        public ?array $settings = null,
        public ?Collection $adSets = null,
        public ?array $metadata = null,
    ) {}

    public static function fromModel(\App\Models\Campaign $campaign): self
    {
        return new self(
            id: $campaign->id ? (string) $campaign->id : null,
            name: $campaign->name,
            objective: $campaign->type ?? 'conversions',
            status: $campaign->status,
            clientId: $campaign->client_id ? (string) $campaign->client_id : null,
            budget: $campaign->budget?->floatValue(),
            budgetType: $campaign->daily_budget ? 'daily' : 'lifetime',
            startDate: $campaign->start_date?->toCarbon(),
            endDate: $campaign->end_date?->toCarbon(),
            targeting: $campaign->audience_targeting,
            settings: $campaign->settings,
            metadata: [
                'platform' => $campaign->platform,
                'channels' => $campaign->channels,
                'bidding_strategy' => $campaign->bidding_strategy,
                'bid_amount' => $campaign->bid_amount?->floatValue(),
            ],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'objective' => $this->objective,
            'status' => $this->status,
            'client_id' => $this->clientId,
            'budget' => $this->budget,
            'budget_type' => $this->budgetType,
            'start_date' => $this->startDate?->toISOString(),
            'end_date' => $this->endDate?->toISOString(),
            'targeting' => $this->targeting,
            'settings' => $this->settings,
            'metadata' => $this->metadata,
        ];
    }
}

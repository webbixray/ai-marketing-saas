<?php

namespace App\Domain\Campaign\DTO;

use Illuminate\Support\Collection;

readonly class CreativeData
{
    public function __construct(
        public string $name,
        public string $type, // image, video, carousel, collection, story
        public string $adSetId,
        public array $content, // {primary_text, headline, description, media_url, thumbnail_url, call_to_action}
        public ?string $id = null,
        public ?string $status = 'active',
        public ?array $previewUrls = null,
        public ?array $metadata = null, // aspect_ratio, duration, file_size, etc.
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'ad_set_id' => $this->adSetId,
            'content' => $this->content,
            'status' => $this->status,
            'preview_urls' => $this->previewUrls,
            'metadata' => $this->metadata,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            name: $data['name'],
            type: $data['type'],
            adSetId: $data['ad_set_id'] ?? $data['adSetId'],
            content: $data['content'],
            status: $data['status'] ?? 'active',
            previewUrls: $data['preview_urls'] ?? $data['previewUrls'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }
}

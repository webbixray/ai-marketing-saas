<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Facade;
use OpenAI\Laravel\AI\AI;

class LLMService
{
    protected $ai;

    public function __construct()
    {
        $this->ai = AI::get();
    }

    public function chat(array $messages, array $options = []): array
    {
        $model = $this->ai->model(
            config('ai.default_provider') ?? 'openai',
            config('ai.models.' . (config('ai.default_provider') ?? 'openai') ?? 'gpt-4o-mini')
        );

        return $model->chat([
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 2000,
        ]);
    }

    public function complete(string $prompt, array $options = []): string
    {
        $response = $this->chat([
            ['role' => 'user', 'content' => $prompt],
        ]);

        return $response['choices'][0]['message']['content'] ?? '';
    }

    public function embeddings(array $input): array
    {
        $model = $this->ai->model(
            config('ai.default_provider') ?? 'openai',
            config('ai.models.' . (config('ai.default_provider') ?? 'openai') ?? 'text-embedding-3-small')
        );

        return $model->embeddings([
            'input' => $input,
        ]);
    }

    public function countTokens(string $text): int
    {
        return $this->ai->countTokens($text);
    }

    public function summarize(string $text, string $tone = 'professional'): string
    {
        $prompt = "Summarize the following text in a {$tone} tone. Keep it concise and actionable:

{$text}";

        return $this->complete($prompt);
    }

    public function extractEntities(string $text): array
    {
        $prompt = "Extract all key entities (people, organizations, dates, amounts, locations) from the following text. Return as JSON array:

{$text}";

        $result = json_decode($this->complete($prompt), true);
        return $result ?? [];
    }
}

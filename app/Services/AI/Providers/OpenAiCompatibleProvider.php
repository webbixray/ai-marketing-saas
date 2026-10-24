<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Exceptions\LlmException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI-compatible chat/completions provider.
 *
 * Works with OpenAI, Ollama, vLLM, or any endpoint speaking the
 * /chat/completions API. Config comes from ai.llm.providers.<name>:
 * base_url, api_key, model; timeout/retries from ai.llm.
 *
 * Retries 429/5xx with exponential backoff, then throws LlmException.
 * Never called in the test suite (tests run the mock provider).
 */
class OpenAiCompatibleProvider implements LlmProvider
{
    protected const RETRYABLE_STATUSES = [429, 500, 502, 503, 504];

    public function __construct(
        protected array $config,
        protected int $timeout = 60,
        protected int $maxRetries = 2,
    ) {
    }

    public function name(): string
    {
        return (string) ($this->config['name'] ?? 'openai');
    }

    public function chat(array $messages, array $options = []): array
    {
        $apiKey = $this->config['api_key'] ?? null;
        if (blank($apiKey)) {
            throw new LlmException(sprintf(
                'No API key configured for LLM provider [%s]. Set the corresponding AI_*_API_KEY environment variable.',
                $this->name()
            ));
        }

        $payload = [
            'model' => $options['model'] ?? $this->config['model'] ?? 'gpt-4o-mini',
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 1000,
        ];

        $lastError = 'unknown error';
        $attempts = max(1, $this->maxRetries + 1);

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            if ($attempt > 1) {
                // Exponential backoff: 200ms, 400ms, ...
                usleep((int) (200_000 * (2 ** ($attempt - 2))));
            }

            try {
                $response = Http::baseUrl($this->config['base_url'])
                    ->withToken($apiKey)
                    ->timeout($this->timeout)
                    ->acceptJson()
                    ->post('/chat/completions', $payload);
            } catch (ConnectionException $e) {
                $lastError = 'connection failed: ' . $e->getMessage();
                continue;
            }

            if ($response->successful()) {
                $body = $response->json();

                if (! is_array($body) || ! isset($body['choices'][0]['message']['content'])) {
                    throw new LlmException('LLM provider returned a malformed response.');
                }

                return $body;
            }

            if (in_array($response->status(), self::RETRYABLE_STATUSES, true) && $attempt < $attempts) {
                $lastError = "HTTP {$response->status()}";
                continue;
            }

            // Non-retryable (401/403/400/404) or retries exhausted
            $bodyPreview = mb_substr((string) $response->body(), 0, 300);
            throw new LlmException(sprintf(
                'LLM provider [%s] request failed (HTTP %d): %s',
                $this->name(),
                $response->status(),
                $bodyPreview !== '' ? $bodyPreview : 'no response body'
            ));
        }

        throw new LlmException(sprintf(
            'LLM provider [%s] request failed after %d attempts: %s',
            $this->name(),
            $attempts,
            $lastError
        ));
    }
}

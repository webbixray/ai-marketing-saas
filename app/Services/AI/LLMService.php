<?php

namespace App\Services\AI;

use App\Services\AI\Exceptions\LlmException;
use App\Services\AI\Providers\LlmProvider;
use App\Services\AI\Providers\LlmProviderFactory;

/**
 * Compatibility facade over the provider-abstracted LLM layer.
 *
 * Previously this class imported OpenAI\Laravel\AI\AI — a package that is
 * NOT in composer.json (instantiation was a fatal error) — and read config
 * keys (ai.default_provider, ai.models.*) that did not match config/ai.php.
 *
 * It now delegates to LlmProviderFactory, which resolves
 * config('ai.llm.default_provider') ('mock' in the testing environment via
 * phpunit.xml). The injected-config constructor signature is preserved for
 * existing bindings (AIServiceProvider, app/Services/AI.php); provider
 * selection is driven by ai.llm.*, not by injected config.
 */
class LLMService
{
    protected ?LlmProvider $provider = null;

    /**
     * @param array $config accepted for backward compatibility; provider
     *                      selection reads config('ai.llm.*') instead.
     */
    public function __construct(array $config = [])
    {
    }

    protected function provider(): LlmProvider
    {
        return $this->provider ??= LlmProviderFactory::make();
    }

    /**
     * Run a chat completion via the configured provider.
     *
     * @throws LlmException on provider failure
     */
    public function chat(array $messages, array $options = []): array
    {
        return $this->provider()->chat($messages, $options);
    }

    /**
     * Single-turn completion returning the assistant message content.
     *
     * @throws LlmException on provider failure
     */
    public function complete(string $prompt, array $options = []): string
    {
        $response = $this->chat([
            ['role' => 'user', 'content' => $prompt],
        ], $options);

        return (string) ($response['choices'][0]['message']['content'] ?? '');
    }

    /**
     * Single-turn completion expecting a JSON payload from the model.
     *
     * @throws LlmException on provider failure or non-JSON output
     */
    public function completeJson(string $prompt, array $schema = [], array $options = []): array
    {
        $raw = $this->complete($prompt, $options);

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            throw new LlmException('Model did not return valid JSON.');
        }

        return $decoded;
    }

    /**
     * Rough token estimate (~4 chars per word). Good enough for budgets and
     * logs; not a tokenizer.
     */
    public function countTokens(string $text): int
    {
        return max(1, (int) floor(str_word_count($text) / 4));
    }

    public function summarize(string $text, string $tone = 'professional'): string
    {
        $prompt = "Summarize the following text in a {$tone} tone. Keep it concise and actionable:\n\n{$text}";

        return $this->complete($prompt);
    }

    public function extractEntities(string $text): array
    {
        $prompt = "Extract all key entities (people, organizations, dates, amounts, locations) from the following text. Return as JSON array:\n\n{$text}";

        return $this->completeJson($prompt);
    }

    /**
     * @throws LlmException image generation is not part of the chat provider
     *                      abstraction (out of scope per AI_SURFACE_SPEC.md)
     */
    public function generateImage(string $prompt, array $options = []): array
    {
        throw new LlmException('Image generation is not supported by the LLM provider abstraction.');
    }
}

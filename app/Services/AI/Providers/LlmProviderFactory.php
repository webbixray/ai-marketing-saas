<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Exceptions\LlmException;

/**
 * Resolves the active LLM provider from config.
 *
 * Precedence: explicit argument > ai.llm.default_provider > 'mock'.
 * 'mock' maps to MockProvider; anything else must be an entry in
 * ai.llm.providers (openai, ollama, vllm, ...).
 */
class LlmProviderFactory
{
    public static function make(?string $provider = null): LlmProvider
    {
        $name = $provider ?: (string) config('ai.llm.default_provider', 'mock');

        if ($name === MockProvider::NAME) {
            return new MockProvider();
        }

        $providerConfig = config("ai.llm.providers.{$name}");

        if (! is_array($providerConfig)) {
            throw new LlmException(sprintf(
                'Unknown LLM provider [%s]. Available: %s',
                $name,
                implode(', ', array_merge([MockProvider::NAME], array_keys((array) config('ai.llm.providers', []))))
            ));
        }

        $providerConfig['name'] = $name;

        return new OpenAiCompatibleProvider(
            $providerConfig,
            (int) config('ai.llm.timeout', 60),
            (int) config('ai.llm.max_retries', 2)
        );
    }
}

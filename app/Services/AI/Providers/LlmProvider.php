<?php

namespace App\Services\AI\Providers;

/**
 * Minimal LLM provider abstraction (see docs/launch/AI_SURFACE_SPEC.md).
 *
 * Implementations must return the OpenAI-compatible chat response shape:
 *   ['choices' => [['message' => ['role' => 'assistant', 'content' => '...']]],
 *    'usage' => ['prompt_tokens' => int, 'completion_tokens' => int],
 *    'model' => string]
 *
 * Failures must throw LlmException with a clean message — never raw HTTP.
 */
interface LlmProvider
{
    /**
     * Run a chat completion.
     *
     * @param  array  $messages  [['role' => 'system'|'user'|'assistant', 'content' => string], ...]
     * @param  array  $options   provider options (max_tokens, temperature, type, ...)
     * @return array OpenAI-compatible response shape
     *
     * @throws \App\Services\AI\Exceptions\LlmException
     */
    public function chat(array $messages, array $options = []): array;

    /**
     * Provider name (as it appears in ai.llm.providers).
     */
    public function name(): string;
}

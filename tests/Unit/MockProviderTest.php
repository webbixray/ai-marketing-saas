<?php

namespace Tests\Unit;

use App\Services\AI\Exceptions\LlmException;
use App\Services\AI\Providers\MockProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the deterministic mock LLM provider.
 * MockProvider is pure PHP (no Laravel app needed) by design.
 */
class MockProviderTest extends TestCase
{
    public function test_chat_returns_openai_shaped_response(): void
    {
        $response = (new MockProvider())->chat([
            ['role' => 'user', 'content' => 'Write a facebook ad for coffee'],
        ], ['type' => 'ad_copy']);

        $this->assertArrayHasKey('choices', $response);
        $this->assertSame('assistant', $response['choices'][0]['message']['role']);
        $this->assertNotEmpty($response['choices'][0]['message']['content']);
        $this->assertIsInt($response['usage']['prompt_tokens']);
        $this->assertIsInt($response['usage']['completion_tokens']);
        $this->assertSame('mock-1', $response['model']);
    }

    public function test_chat_is_deterministic(): void
    {
        $provider = new MockProvider();
        $messages = [['role' => 'user', 'content' => 'Write an email about a sale']];

        $first = $provider->chat($messages, ['type' => 'email']);
        $second = $provider->chat($messages, ['type' => 'email']);

        $this->assertSame($first, $second);
    }

    public function test_each_type_gets_its_own_canned_template(): void
    {
        $provider = new MockProvider();

        $ad = $provider->chat([['role' => 'user', 'content' => 'x']], ['type' => 'ad_copy']);
        $this->assertStringContainsString('Headline:', $ad['choices'][0]['message']['content']);

        $email = $provider->chat([['role' => 'user', 'content' => 'x']], ['type' => 'email']);
        $this->assertStringContainsString('Subject:', $email['choices'][0]['message']['content']);

        $landing = $provider->chat([['role' => 'user', 'content' => 'x']], ['type' => 'landing_page']);
        $this->assertStringContainsString('CTA:', $landing['choices'][0]['message']['content']);
    }

    public function test_unknown_type_returns_generic_response(): void
    {
        $provider = new MockProvider();

        $response = $provider->chat([
            ['role' => 'user', 'content' => 'Tell me about the spring sale'],
        ]);

        $content = $response['choices'][0]['message']['content'];
        $this->assertStringContainsString('spring sale', $content);
        $this->assertStringContainsString('mock output', $content);
    }

    public function test_empty_messages_throws_llm_exception(): void
    {
        $this->expectException(LlmException::class);

        (new MockProvider())->chat([]);
    }
}

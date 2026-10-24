<?php

namespace App\Services\AI\Exceptions;

use RuntimeException;

/**
 * Thrown when an LLM provider call fails (network, auth, rate limit,
 * malformed response). Carries a clean, user-presentable message.
 */
class LlmException extends RuntimeException
{
}

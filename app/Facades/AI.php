<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for the AI service layer.
 *
 * @method static mixed call(string $prompt, array $options = [])
 */
class AIFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'AI';
    }
}
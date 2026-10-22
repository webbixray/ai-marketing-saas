<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Get the API base URL with version prefix.
     */
    protected function apiUrl(string $path): string
    {
        return '/api/v1' . $path;
    }
}

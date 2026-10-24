<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Health module: /api/v1/health, /health/live, /health/ready.
 * All three are unauthenticated (load-balancer probes).
 */
class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_ok(): void
    {
        $response = $this->getJson($this->apiUrl('/health'));

        $response->assertOk()
            ->assertJsonStructure(['status', 'timestamp', 'version', 'environment'])
            ->assertJsonPath('status', 'ok');
    }

    public function test_live_probe_reports_alive(): void
    {
        $this->getJson($this->apiUrl('/health/live'))
            ->assertOk()
            ->assertJsonStructure(['status', 'timestamp'])
            ->assertJsonPath('status', 'alive');
    }

    public function test_ready_probe_reports_dependency_checks(): void
    {
        // Redis may or may not be reachable in the test environment, so
        // accept either overall status, but require consistent reporting:
        // status 'ready' iff every check is 'ok'.
        $response = $this->getJson($this->apiUrl('/health/ready'));

        $this->assertContains($response->status(), [200, 503]);
        $response->assertJsonStructure(['status', 'timestamp', 'checks' => ['database', 'cache', 'redis']]);

        $checks = $response->json('checks');
        $allOk = $checks['database'] === 'ok' && $checks['cache'] === 'ok' && $checks['redis'] === 'ok';

        $this->assertSame($allOk ? 'ready' : 'not_ready', $response->json('status'));
        $this->assertSame($allOk ? 200 : 503, $response->status());
    }
}

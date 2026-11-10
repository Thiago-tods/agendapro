<?php

namespace Tests\Feature\System;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_check_reports_ok_when_database_is_reachable(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonStructure(['status', 'checks' => ['database'], 'timestamp']);
    }

    public function test_health_check_reports_degraded_without_leaking_details_when_database_fails(): void
    {
        config([
            'database.connections.pgsql.port' => 1,
            'database.connections.pgsql.connect_timeout' => 1,
        ]);
        DB::purge('pgsql');

        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.database', 'unavailable');

        $this->assertStringNotContainsString('SQLSTATE', (string) $response->getContent());
    }

    public function test_unknown_api_route_returns_json_404_without_accept_header(): void
    {
        $this->get('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['message']);
    }

    public function test_api_is_only_served_under_the_v1_prefix(): void
    {
        $this->getJson('/api/health')->assertNotFound();
    }

    public function test_csrf_cookie_endpoint_is_available_for_the_spa(): void
    {
        $this->get('/sanctum/csrf-cookie')
            ->assertNoContent()
            ->assertCookie('XSRF-TOKEN');
    }

    public function test_cors_allows_credentialed_requests_from_the_frontend_origin(): void
    {
        $origin = config('cors.allowed_origins')[0];

        $this->withHeaders(['Origin' => $origin])
            ->getJson('/api/v1/health')
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_cors_rejects_unknown_origins(): void
    {
        $response = $this->withHeaders(['Origin' => 'https://evil.example'])
            ->getJson('/api/v1/health');

        // The browser blocks the response because the allowed origin does not match the caller.
        $this->assertNotSame('https://evil.example', $response->headers->get('Access-Control-Allow-Origin'));
    }
}

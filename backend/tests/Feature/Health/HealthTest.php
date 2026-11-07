<?php

namespace Tests\Feature\Health;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_is_public_and_returns_uncached_json(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok'])
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_health_works_without_a_database_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['database.default' => 'unconfigured-health-test-connection']);

        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }
}

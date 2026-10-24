<?php

namespace Tests\Feature\Docs;

use Tests\TestCase;

class DocumentationTest extends TestCase
{
    public function test_ui_page_is_served(): void
    {
        $this->get('/api/documentation')
            ->assertOk()
            ->assertSee('swagger-ui', false);
    }

    public function test_spec_is_valid_openapi_json(): void
    {
        $response = $this->getJson('/api/documentation/openapi.json')->assertOk();

        $spec = $response->json();

        $this->assertArrayHasKey('openapi', $spec);
        $this->assertSame('WatchWise API', $spec['info']['title']);
        $this->assertArrayHasKey('cookieAuth', $spec['components']['securitySchemes']);
        $this->assertArrayHasKey('bearerAuth', $spec['components']['securitySchemes']);
    }

    public function test_documentation_is_hidden_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->get('/api/documentation')->assertNotFound();
        $this->getJson('/api/documentation/openapi.json')->assertNotFound();
    }
}

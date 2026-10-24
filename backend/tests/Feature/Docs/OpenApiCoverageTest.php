<?php

namespace Tests\Feature\Docs;

use App\Docs\Services\OpenApiSpec;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OpenApiCoverageTest extends TestCase
{
    private const HTTP_METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    /**
     * @return array<int, string> entries like "get /api/movies"
     */
    private function routeOperations(): array
    {
        $operations = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, 'api/') || in_array($route->getName(), ['documentation', 'documentation.spec'], true)) {
                continue;
            }

            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }
                $operations[] = strtolower($method).' /'.$uri;
            }
        }

        sort($operations);

        return array_values(array_unique($operations));
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<int, string>
     */
    private function specOperations(array $spec): array
    {
        $operations = [];

        foreach ($spec['paths'] ?? [] as $path => $methods) {
            foreach (array_keys($methods) as $method) {
                if (in_array($method, self::HTTP_METHODS, true)) {
                    $operations[] = $method.' '.$path;
                }
            }
        }

        sort($operations);

        return $operations;
    }

    public function test_every_api_route_is_documented(): void
    {
        $spec = (new OpenApiSpec)->build();

        $missing = array_diff($this->routeOperations(), $this->specOperations($spec));

        $this->assertSame([], array_values($missing), "Routes missing from the OpenAPI spec:\n".implode("\n", $missing));
    }

    public function test_every_documented_operation_has_a_route(): void
    {
        $spec = (new OpenApiSpec)->build();

        $unknown = array_diff($this->specOperations($spec), $this->routeOperations());

        $this->assertSame([], array_values($unknown), "Spec operations without a route:\n".implode("\n", $unknown));
    }

    public function test_every_operation_has_summary_tags_and_responses(): void
    {
        $spec = (new OpenApiSpec)->build();
        $incomplete = [];

        foreach ($spec['paths'] ?? [] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                if (! in_array($method, self::HTTP_METHODS, true)) {
                    continue;
                }
                if (empty($operation['summary']) || empty($operation['tags']) || empty($operation['responses'])) {
                    $incomplete[] = $method.' '.$path;
                }
            }
        }

        $this->assertSame([], $incomplete, "Operations missing summary, tags or responses:\n".implode("\n", $incomplete));
    }
}

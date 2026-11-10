<?php

namespace App\Docs\Services;

use RuntimeException;

class OpenApiSpec
{
    /**
     * Merge openapi/base.json with every openapi/paths/*.json file.
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $directory = base_path('openapi');
        $spec = $this->readJson($directory.'/base.json');

        $files = glob($directory.'/paths/*.json') ?: [];
        sort($files);

        foreach ($files as $file) {
            $part = $this->readJson($file);

            foreach ($part['paths'] ?? [] as $path => $operations) {
                foreach ($operations as $method => $operation) {
                    if (isset($spec['paths'][$path][$method])) {
                        throw new RuntimeException(
                            'Duplicate OpenAPI operation '.strtoupper($method).' '.$path.' in '.basename($file)
                        );
                    }
                    $spec['paths'][$path][$method] = $operation;
                }
            }

            foreach ($part['components']['schemas'] ?? [] as $name => $schema) {
                if (isset($spec['components']['schemas'][$name])) {
                    throw new RuntimeException('Duplicate OpenAPI schema '.$name.' in '.basename($file));
                }
                $spec['components']['schemas'][$name] = $schema;
            }
        }

        return $this->documentEmailVerification($spec);
    }

    /**
     * Every signed-in operation outside /api/auth sits behind `verified.email`, so each one gets the
     * `email_unverified` 403 here instead of repeating it in every paths file.
     *
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    private function documentEmailVerification(array $spec): array
    {
        foreach ($spec['paths'] ?? [] as $path => $operations) {
            if (str_starts_with($path, '/api/auth/')) {
                continue;
            }

            foreach ($operations as $method => $operation) {
                if (empty($operation['security'])) {
                    continue;
                }

                $forbidden = $operation['responses']['403'] ?? null;
                $spec['paths'][$path][$method]['responses']['403'] = $forbidden === null
                    ? ['$ref' => '#/components/responses/EmailUnverified']
                    : [...$forbidden, 'description' => $forbidden['description'].' Also returned with `code: email_unverified` when the signed-in account has not verified its email.'];
                ksort($spec['paths'][$path][$method]['responses']);
            }
        }

        return $spec;
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $file): array
    {
        $decoded = json_decode((string) file_get_contents($file), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Invalid OpenAPI JSON file: '.basename($file));
        }

        return $decoded;
    }
}

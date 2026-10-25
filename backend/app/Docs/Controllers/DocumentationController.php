<?php

namespace App\Docs\Controllers;

use App\Docs\Services\OpenApiSpec;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class DocumentationController
{
    /**
     * Swagger UI page (local and testing environments only).
     */
    public function ui(): View
    {
        $this->abortUnlessDocsEnabled();

        return view('docs.swagger');
    }

    /**
     * Merged OpenAPI specification (local and testing environments only).
     */
    public function spec(OpenApiSpec $spec): JsonResponse
    {
        $this->abortUnlessDocsEnabled();

        return response()->json($spec->build());
    }

    private function abortUnlessDocsEnabled(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
    }
}

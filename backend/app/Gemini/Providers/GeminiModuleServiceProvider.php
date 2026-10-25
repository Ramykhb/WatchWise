<?php

namespace App\Gemini\Providers;

use App\Gemini\InterfaceServices\GeminiServiceInterface;
use App\Gemini\Services\GeminiService;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the optional Gemini client.
 */
class GeminiModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        GeminiServiceInterface::class => GeminiService::class,
    ];
}

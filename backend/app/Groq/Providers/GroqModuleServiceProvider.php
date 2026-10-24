<?php

namespace App\Groq\Providers;

use App\Groq\InterfaceServices\GroqServiceInterface;
use App\Groq\Services\GroqService;
use Illuminate\Support\ServiceProvider;

/** Binds the Groq module's service contract. */
class GroqModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        GroqServiceInterface::class => GroqService::class,
    ];
}

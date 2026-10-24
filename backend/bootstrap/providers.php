<?php

use App\Auth\Providers\AuthModuleServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthorizationServiceProvider;
use App\Tmdb\Providers\TmdbModuleServiceProvider;

return [
    AppServiceProvider::class,
    AuthorizationServiceProvider::class,
    AuthModuleServiceProvider::class,
    TmdbModuleServiceProvider::class,
];

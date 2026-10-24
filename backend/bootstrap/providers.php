<?php

use App\Auth\Providers\AuthModuleServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthorizationServiceProvider;

return [
    AppServiceProvider::class,
    AuthorizationServiceProvider::class,
    AuthModuleServiceProvider::class,
];

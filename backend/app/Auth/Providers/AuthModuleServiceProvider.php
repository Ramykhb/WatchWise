<?php

namespace App\Auth\Providers;

use App\Auth\InterfaceRepository\PasswordResetRepositoryInterface;
use App\Auth\InterfaceRepository\RefreshTokenRepositoryInterface;
use App\Auth\InterfaceRepository\SocialAccountRepositoryInterface;
use App\Auth\InterfaceRepository\UserRepositoryInterface;
use App\Auth\InterfaceServices\AuthServiceInterface;
use App\Auth\InterfaceServices\EmailVerificationServiceInterface;
use App\Auth\InterfaceServices\PasswordResetServiceInterface;
use App\Auth\InterfaceServices\SocialAuthServiceInterface;
use App\Auth\Repository\PasswordResetRepository;
use App\Auth\Repository\RefreshTokenRepository;
use App\Auth\Repository\SocialAccountRepository;
use App\Auth\Repository\UserRepository;
use App\Auth\Services\AuthService;
use App\Auth\Services\EmailVerificationService;
use App\Auth\Services\PasswordResetService;
use App\Auth\Services\SocialAuthService;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the Auth module's interfaces to their implementations.
 */
class AuthModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        UserRepositoryInterface::class => UserRepository::class,
        RefreshTokenRepositoryInterface::class => RefreshTokenRepository::class,
        PasswordResetRepositoryInterface::class => PasswordResetRepository::class,
        SocialAccountRepositoryInterface::class => SocialAccountRepository::class,

        AuthServiceInterface::class => AuthService::class,
        EmailVerificationServiceInterface::class => EmailVerificationService::class,
        PasswordResetServiceInterface::class => PasswordResetService::class,
        SocialAuthServiceInterface::class => SocialAuthService::class,
    ];
}

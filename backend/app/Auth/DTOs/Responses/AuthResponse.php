<?php

namespace App\Auth\DTOs\Responses;

use App\Auth\Support\TokenPair;
use Carbon\CarbonInterface;

/** Result of a login, registration or refresh: the user plus the tokens the controller turns into cookies. */
final readonly class AuthResponse
{
    public function __construct(
        public UserResponse $user,
        public string $accessToken,
        public CarbonInterface $accessExpiresAt,
        public string $refreshToken,
        public CarbonInterface $refreshExpiresAt,
    ) {}

    public static function fromTokenPair(TokenPair $pair): self
    {
        return new self(
            UserResponse::fromModel($pair->user),
            $pair->accessToken,
            $pair->accessExpiresAt,
            $pair->refreshToken,
            $pair->refreshExpiresAt,
        );
    }

    /**
     * The JSON body. The tokens themselves only travel in cookies.
     *
     * @return array{user: UserResponse, expires_at: string}
     */
    public function body(): array
    {
        return [
            'user' => $this->user,
            'expires_at' => $this->accessExpiresAt->format(DATE_ATOM),
        ];
    }
}

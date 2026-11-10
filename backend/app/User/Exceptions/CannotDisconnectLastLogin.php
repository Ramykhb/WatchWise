<?php

namespace App\User\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * The provider is the only way left to sign in: the account never chose a password of its own and
 * has no other provider linked. Rendered as a 422 on `provider`.
 */
class CannotDisconnectLastLogin extends RuntimeException
{
    private const LABELS = ['google' => 'Google', 'github' => 'GitHub', 'discord' => 'Discord'];

    public function __construct(public readonly string $provider)
    {
        $label = self::LABELS[$provider] ?? ucfirst($provider);

        parent::__construct("{$label} is the only way you can sign in. Create a password or connect another account before disconnecting it.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['provider' => [$this->getMessage()]],
        ], 422);
    }
}

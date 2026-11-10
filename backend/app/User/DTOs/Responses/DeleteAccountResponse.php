<?php

namespace App\User\DTOs\Responses;

use JsonSerializable;

/**
 * What DELETE /api/me did: deleted the account straight away (the password proved ownership), or mailed a
 * confirmation link to the account's address and deleted nothing yet.
 */
final readonly class DeleteAccountResponse implements JsonSerializable
{
    private function __construct(
        public bool $deleted,
        private ?string $mailedTo,
    ) {}

    public static function deleted(): self
    {
        return new self(true, null);
    }

    public static function confirmationSent(string $email): self
    {
        return new self(false, $email);
    }

    /**
     * @return array{message: string, deletion_pending?: bool}
     */
    public function jsonSerialize(): array
    {
        if ($this->deleted) {
            return ['message' => 'Account deleted.'];
        }

        return [
            'message' => "We sent a link to {$this->mailedTo}. Your account is deleted once you open it.",
            'deletion_pending' => true,
        ];
    }
}

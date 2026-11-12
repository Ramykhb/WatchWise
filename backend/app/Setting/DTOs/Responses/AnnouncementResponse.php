<?php

namespace App\Setting\DTOs\Responses;

use JsonSerializable;

/** The site announcement visitors see while it is switched on and has not expired. */
final readonly class AnnouncementResponse implements JsonSerializable
{
    public function __construct(
        public string $message,
        public string $level,
        public ?string $expiresAt,
    ) {}

    /**
     * @return array{message: string, level: string, expires_at: string|null}
     */
    public function jsonSerialize(): array
    {
        return [
            'message' => $this->message,
            'level' => $this->level,
            'expires_at' => $this->expiresAt,
        ];
    }
}

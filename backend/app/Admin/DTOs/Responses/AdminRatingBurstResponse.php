<?php

namespace App\Admin\DTOs\Responses;

use App\Models\Review;
use Carbon\CarbonInterface;
use JsonSerializable;

/** A member who left many star ratings inside the window, with how many. */
final readonly class AdminRatingBurstResponse implements JsonSerializable
{
    public function __construct(
        public int $userId,
        public ?string $userName,
        public ?string $userUsername,
        public ?string $userCreatedAt,
        public int $count,
        public CarbonInterface $windowStart,
        public CarbonInterface $windowEnd,
    ) {}

    /**
     * `$row` is a burst row from the rating service: it carries the member's `user` and the rating count in `total`.
     */
    public static function fromModel(Review $row, CarbonInterface $windowStart, CarbonInterface $windowEnd): self
    {
        return new self(
            userId: (int) $row->user_id,
            userName: $row->user?->name,
            userUsername: $row->user?->username,
            userCreatedAt: $row->user?->created_at?->toJSON(),
            count: (int) $row->getAttribute('total'),
            windowStart: $windowStart,
            windowEnd: $windowEnd,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'user' => ['id' => $this->userId, 'name' => $this->userName, 'username' => $this->userUsername, 'created_at' => $this->userCreatedAt],
            'count' => $this->count,
            'window_start' => $this->windowStart->toJSON(),
            'window_end' => $this->windowEnd->toJSON(),
        ];
    }
}

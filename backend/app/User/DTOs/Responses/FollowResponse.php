<?php

namespace App\User\DTOs\Responses;

use JsonSerializable;

/** The result of following or unfollowing someone: the new state and their follower count. */
final readonly class FollowResponse implements JsonSerializable
{
    public function __construct(
        public bool $following,
        public int $followers,
    ) {}

    /**
     * @return array{following: bool, followers: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'following' => $this->following,
            'followers' => $this->followers,
        ];
    }
}

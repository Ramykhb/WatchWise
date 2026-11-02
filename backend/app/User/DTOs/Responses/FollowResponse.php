<?php

namespace App\User\DTOs\Responses;

use JsonSerializable;

/**
 * The result of following or unfollowing someone: the new state (following, or `requested` while a
 * followers-only or private profile hasn't answered yet) and their follower count.
 */
final readonly class FollowResponse implements JsonSerializable
{
    public function __construct(
        public bool $following,
        public bool $requested,
        public int $followers,
    ) {}

    /**
     * @return array{following: bool, requested: bool, followers: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'following' => $this->following,
            'requested' => $this->requested,
            'followers' => $this->followers,
        ];
    }
}

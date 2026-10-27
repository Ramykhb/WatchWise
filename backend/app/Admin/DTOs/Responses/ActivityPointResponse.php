<?php

namespace App\Admin\DTOs\Responses;

use JsonSerializable;

/** What members did on one day. */
final readonly class ActivityPointResponse implements JsonSerializable
{
    public function __construct(
        public string $date,
        public int $signups,
        public int $ratings,
        public int $reviews,
        public int $lists,
        public int $watches,
        public int $messages,
    ) {}

    /**
     * @return array<string, int|string>
     */
    public function jsonSerialize(): array
    {
        return [
            'date' => $this->date,
            'signups' => $this->signups,
            'ratings' => $this->ratings,
            'reviews' => $this->reviews,
            'lists' => $this->lists,
            'watches' => $this->watches,
            'messages' => $this->messages,
        ];
    }
}

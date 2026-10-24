<?php

namespace App\Tmdb\DTOs\Responses;

use JsonSerializable;

/** A YouTube trailer, with links to watch it and to embed it on the page. */
final readonly class TrailerResponse implements JsonSerializable
{
    public function __construct(
        public string $name,
        public string $youtubeKey,
    ) {}

    /**
     * @return array{name: string, youtube_key: string, url: string, embed_url: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'youtube_key' => $this->youtubeKey,
            'url' => "https://www.youtube.com/watch?v={$this->youtubeKey}",
            'embed_url' => "https://www.youtube.com/embed/{$this->youtubeKey}",
        ];
    }
}

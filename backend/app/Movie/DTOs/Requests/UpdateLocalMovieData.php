<?php

namespace App\Movie\DTOs\Requests;

/** Fields an admin changes on a local movie; a null field is left as it is. */
final readonly class UpdateLocalMovieData
{
    public function __construct(
        public ?string $title = null,
        public ?string $overview = null,
        public ?bool $isHidden = null,
        public ?bool $isFeatured = null,
    ) {}

    /**
     * @param  array{title?: string, overview?: string|null, is_hidden?: bool, is_featured?: bool}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            $validated['title'] ?? null,
            $validated['overview'] ?? null,
            isset($validated['is_hidden']) ? (bool) $validated['is_hidden'] : null,
            isset($validated['is_featured']) ? (bool) $validated['is_featured'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        return array_filter([
            'title' => $this->title,
            'overview' => $this->overview,
            'is_hidden' => $this->isHidden,
            'is_featured' => $this->isFeatured,
        ], fn (mixed $value): bool => $value !== null);
    }
}

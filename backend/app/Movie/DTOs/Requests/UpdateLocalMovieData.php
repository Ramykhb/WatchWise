<?php

namespace App\Movie\DTOs\Requests;

/**
 * Fields an admin changes on a local movie. A null title or flag is left as it is; the overview can be
 * cleared, so whether it was supplied is tracked apart from its value.
 */
final readonly class UpdateLocalMovieData
{
    public function __construct(
        public ?string $title = null,
        public ?string $overview = null,
        public ?bool $isHidden = null,
        public ?bool $isFeatured = null,
        public bool $overviewSupplied = false,
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
            array_key_exists('overview', $validated),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        $changes = array_filter([
            'title' => $this->title,
            'is_hidden' => $this->isHidden,
            'is_featured' => $this->isFeatured,
        ], fn (mixed $value): bool => $value !== null);

        if ($this->overviewSupplied) {
            $changes['overview'] = $this->overview;
        }

        return $changes;
    }
}

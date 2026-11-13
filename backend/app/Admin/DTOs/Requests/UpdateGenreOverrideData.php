<?php

namespace App\Admin\DTOs\Requests;

/**
 * The parts of a genre override staff submitted. The name and the position can be cleared with null, so whether
 * each was supplied is tracked apart from its value; the hidden flag cannot be null, so null means it was not sent.
 */
final readonly class UpdateGenreOverrideData
{
    public function __construct(
        public ?string $name = null,
        public bool $nameSupplied = false,
        public ?bool $isHidden = null,
        public ?int $position = null,
        public bool $positionSupplied = false,
    ) {}

    /**
     * @param  array{name?: string|null, is_hidden?: bool, position?: int|string|null}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        $name = isset($validated['name']) ? trim($validated['name']) : null;

        return new self(
            name: $name === '' ? null : $name,
            nameSupplied: array_key_exists('name', $validated),
            isHidden: isset($validated['is_hidden']) ? (bool) $validated['is_hidden'] : null,
            position: isset($validated['position']) ? (int) $validated['position'] : null,
            positionSupplied: array_key_exists('position', $validated),
        );
    }

    /**
     * The submitted fields, keyed by override column.
     *
     * @return array{name?: string|null, is_hidden?: bool, position?: int|null}
     */
    public function changes(): array
    {
        $changes = [];

        if ($this->nameSupplied) {
            $changes['name'] = $this->name;
        }

        if ($this->isHidden !== null) {
            $changes['is_hidden'] = $this->isHidden;
        }

        if ($this->positionSupplied) {
            $changes['position'] = $this->position;
        }

        return $changes;
    }
}

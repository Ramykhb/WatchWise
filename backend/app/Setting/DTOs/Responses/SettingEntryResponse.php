<?php

namespace App\Setting\DTOs\Responses;

use App\Models\AppSetting;
use App\Setting\Support\SettingKey;
use JsonSerializable;

/** One setting as staff see it: its current value and who last changed it, or nulls while it still has its default. */
final readonly class SettingEntryResponse implements JsonSerializable
{
    /**
     * @param  bool|array<string, mixed>  $value
     * @param  array{id: int, name: string, username: string}|null  $updatedBy
     */
    public function __construct(
        public string $key,
        public bool|array $value,
        public ?string $updatedAt,
        public ?array $updatedBy,
    ) {}

    /** Needs the row's `updatedBy` loaded; `$row` is null for a key that was never changed. */
    public static function fromRow(SettingKey $key, ?AppSetting $row): self
    {
        $updater = $row?->updatedBy;

        return new self(
            $key->value,
            $key->resolve($row?->value),
            $row?->updated_at?->toJSON(),
            $updater === null ? null : ['id' => $updater->id, 'name' => $updater->name, 'username' => $updater->username],
        );
    }

    /**
     * @return array{value: bool|array<string, mixed>, updated_at: string|null, updated_by: array{id: int, name: string, username: string}|null}
     */
    public function jsonSerialize(): array
    {
        return [
            'value' => $this->value,
            'updated_at' => $this->updatedAt,
            'updated_by' => $this->updatedBy,
        ];
    }
}

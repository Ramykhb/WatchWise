<?php

namespace App\Admin\DTOs\Responses;

use App\Setting\DTOs\Responses\SettingEntryResponse;
use JsonSerializable;

/** Every app-wide setting by key, each with its value, when it last changed and who changed it. */
final readonly class AdminSettingsResponse implements JsonSerializable
{
    /**
     * @param  list<SettingEntryResponse>  $entries
     */
    public function __construct(public array $entries) {}

    /**
     * @return array{settings: array<string, SettingEntryResponse>}
     */
    public function jsonSerialize(): array
    {
        $settings = [];

        foreach ($this->entries as $entry) {
            $settings[$entry->key] = $entry;
        }

        return ['settings' => $settings];
    }
}

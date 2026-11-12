<?php

namespace Database\Factories;

use App\Models\AppSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppSetting>
 */
class AppSettingFactory extends Factory
{
    protected $model = AppSetting::class;

    /**
     * A closed registration; pass `key` and `value` for anything else.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'registration_open',
            'value' => false,
        ];
    }
}

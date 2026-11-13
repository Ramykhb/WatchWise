<?php

namespace Database\Factories;

use App\Models\GenreOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GenreOverride>
 */
class GenreOverrideFactory extends Factory
{
    protected $model = GenreOverride::class;

    /**
     * A movie genre renamed for display; use the states to hide it, place it or make it a tv genre.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'movie',
            'tmdb_id' => fake()->unique()->numberBetween(1, 99999),
            'name' => fake()->unique()->words(2, true),
            'is_hidden' => false,
            'position' => null,
            'updated_by' => null,
        ];
    }

    public function tv(): static
    {
        return $this->state(['type' => 'tv']);
    }

    public function hidden(): static
    {
        return $this->state(['is_hidden' => true]);
    }

    public function unnamed(): static
    {
        return $this->state(['name' => null]);
    }

    public function at(int $position): static
    {
        return $this->state(['position' => $position]);
    }
}

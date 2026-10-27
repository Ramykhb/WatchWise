<?php

namespace Database\Factories;

use App\Models\LocalMovie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LocalMovie>
 */
class LocalMovieFactory extends Factory
{
    protected $model = LocalMovie::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tmdb_id' => fake()->unique()->numberBetween(1, 900000),
            'title' => fake()->sentence(3),
            'overview' => fake()->paragraph(),
            'poster_path' => '/'.fake()->lexify('??????????').'.jpg',
            'release_date' => fake()->date(),
            'runtime' => fake()->numberBetween(80, 180),
            'genre_ids' => [18],
            'is_hidden' => false,
            'is_featured' => false,
            'synced_at' => now(),
        ];
    }

    public function hidden(): static
    {
        return $this->state(['is_hidden' => true]);
    }

    public function featured(): static
    {
        return $this->state(['is_featured' => true]);
    }
}

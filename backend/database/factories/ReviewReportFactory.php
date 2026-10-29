<?php

namespace Database\Factories;

use App\Models\ReviewReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewReport>
 */
class ReviewReportFactory extends Factory
{
    protected $model = ReviewReport::class;

    /**
     * Pass `review_id` when creating one.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reason' => fake()->randomElement(ReviewReport::REASONS),
            'resolved_at' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(['resolved_at' => now()]);
    }
}

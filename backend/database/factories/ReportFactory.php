<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use App\Report\Support\ReportStatus;
use App\Report\Support\ReportTargetType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    /**
     * A spam report on a review; use `forReview()` or `forTarget()` to point it at something, or pass `target_id` and `target_type`.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporter_id' => User::factory(),
            'target_type' => ReportTargetType::Review,
            'target_id' => fake()->numberBetween(1, 1_000_000),
            'reason' => 'Spam',
            'details' => null,
            'status' => ReportStatus::Open,
            'resolved_at' => null,
        ];
    }

    public function forReview(Review $review): static
    {
        return $this->forTarget(ReportTargetType::Review, $review);
    }

    public function forTarget(ReportTargetType $type, Model $target, ?string $reason = null): static
    {
        return $this->state(fn (): array => [
            'target_type' => $type,
            'target_id' => $target->getKey(),
            'reason' => $reason ?? $type->reasons()[0],
        ]);
    }

    public function resolved(): static
    {
        return $this->state(['status' => ReportStatus::Resolved, 'resolved_at' => now()]);
    }

    public function dismissed(): static
    {
        return $this->state(['status' => ReportStatus::Dismissed, 'resolved_at' => now()]);
    }
}

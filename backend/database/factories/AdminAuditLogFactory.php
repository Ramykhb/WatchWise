<?php

namespace Database\Factories;

use App\Admin\Support\AdminAction;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminAuditLog>
 */
class AdminAuditLogFactory extends Factory
{
    protected $model = AdminAuditLog::class;

    /**
     * An admin suspending a member; pass `actor_id` or use `by()` to pick who acted.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_id' => User::factory()->admin(),
            'actor_role' => 'admin',
            'action' => AdminAction::UserSuspended,
            'subject_type' => AdminAction::UserSuspended->subjectType(),
            'subject_id' => fake()->numberBetween(1, 100000),
            'subject_label' => fake()->userName(),
            'changes' => ['is_active' => ['from' => true, 'to' => false]],
            'created_at' => now(),
        ];
    }

    public function by(User $actor): static
    {
        return $this->state(['actor_id' => $actor->id, 'actor_role' => $actor->role]);
    }

    public function action(AdminAction $action): static
    {
        return $this->state(['action' => $action, 'subject_type' => $action->subjectType(), 'changes' => null]);
    }
}

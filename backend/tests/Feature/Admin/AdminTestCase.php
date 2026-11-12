<?php

namespace Tests\Feature\Admin;

use App\Admin\DTOs\Requests\AdminAuditQuery;
use App\Admin\InterfaceRepository\AdminAuditRepositoryInterface;
use App\Admin\Support\AdminAction;
use App\Models\Review;
use App\Models\User;
use App\Movie\InterfaceServices\MovieCatalogServiceInterface;
use App\Movie\Services\MovieCatalogService;
use Illuminate\Pagination\LengthAwarePaginator;
use RuntimeException;
use Tests\Feature\Social\SocialTestCase;

abstract class AdminTestCase extends SocialTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(MovieCatalogServiceInterface::class, MovieCatalogService::class);
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function review(array $attributes = []): Review
    {
        return Review::create([
            'user_id' => User::factory()->create()->id,
            'tmdb_id' => 550,
            'rating' => 4,
            'content' => 'Great twist.',
            'sentiment' => 'positive',
            'is_approved' => false,
            ...$attributes,
        ]);
    }

    /** Makes every audit insert fail, as a full disk or a lost connection would. */
    protected function failAuditInserts(): void
    {
        $this->app->bind(AdminAuditRepositoryInterface::class, fn () => new class implements AdminAuditRepositoryInterface
        {
            public function insert(User $actor, AdminAction $action, ?int $subjectId, ?string $subjectLabel, ?array $changes): void
            {
                throw new RuntimeException('The audit log is unavailable.');
            }

            public function paginate(AdminAuditQuery $query): LengthAwarePaginator
            {
                throw new RuntimeException('The audit log is unavailable.');
            }
        });
    }
}

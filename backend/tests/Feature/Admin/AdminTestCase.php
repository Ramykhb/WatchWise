<?php

namespace Tests\Feature\Admin;

use App\Admin\DTOs\Requests\AdminAuditQuery;
use App\Admin\InterfaceRepository\AdminAuditRepositoryInterface;
use App\Admin\Repository\AdminAuditRepository;
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

    /** Lets the audit inserts before number `$number` through and makes that one fail, like a connection lost half way. */
    protected function failAuditInsertNumber(int $number): void
    {
        $this->app->singleton(AdminAuditRepositoryInterface::class, fn () => new class(new AdminAuditRepository, $number) implements AdminAuditRepositoryInterface
        {
            private int $inserts = 0;

            public function __construct(private readonly AdminAuditRepository $real, private readonly int $failing) {}

            public function insert(User $actor, AdminAction $action, ?int $subjectId, ?string $subjectLabel, ?array $changes): void
            {
                if (++$this->inserts === $this->failing) {
                    throw new RuntimeException('The audit log is unavailable.');
                }

                $this->real->insert($actor, $action, $subjectId, $subjectLabel, $changes);
            }

            public function paginate(AdminAuditQuery $query): LengthAwarePaginator
            {
                return $this->real->paginate($query);
            }
        });
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

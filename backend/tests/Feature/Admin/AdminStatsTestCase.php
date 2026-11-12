<?php

namespace Tests\Feature\Admin;

use App\Genre\DTOs\Responses\GenreResponse;
use App\Genre\InterfaceServices\GenreServiceInterface;
use App\Models\DiscussionRoom;
use App\Models\Message;
use App\Models\MovieList;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use App\Report\Support\ReportTargetType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Mockery;

/** Shared builders of the admin dashboard suites: one seeded world that has something of every entity the dashboard counts. */
abstract class AdminStatsTestCase extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(GenreServiceInterface::class, Mockery::mock(GenreServiceInterface::class, fn ($mock) => $mock
            ->shouldReceive('allIncludingHidden')->andReturn([new GenreResponse(18, 'Drama')])));
    }

    protected function moderator(): User
    {
        return User::factory()->create(['role' => 'moderator']);
    }

    /** A star rating: a `reviews` row without text, by a new member. */
    protected function rating(int $stars, ?CarbonInterface $createdAt = null, array $attributes = []): Review
    {
        $rating = Review::create([
            'user_id' => User::factory()->create()->id,
            'tmdb_id' => 550,
            'media_type' => 'movie',
            'rating' => $stars,
            'content' => null,
            'sentiment' => null,
            'is_approved' => true,
            ...$attributes,
        ]);

        if ($createdAt !== null) {
            $rating->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

        return $rating;
    }

    protected function listCreatedAt(CarbonInterface $createdAt, bool $isPublic = true): MovieList
    {
        $list = MovieList::create(['user_id' => User::factory()->create()->id, 'title' => 'Heists', 'is_public' => $isPublic]);

        $list->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $list;
    }

    protected function reportOn(ReportTargetType $type, Model $target, string $state = 'open', ?CarbonInterface $resolvedAt = null): Report
    {
        $report = Report::factory()->forTarget($type, $target);

        $report = match ($state) {
            'resolved' => $report->resolved(),
            'dismissed' => $report->dismissed(),
            default => $report,
        };

        return $report->create($resolvedAt === null ? [] : ['resolved_at' => $resolvedAt]);
    }

    /**
     * Everything the dashboard counts, in numbers that tell the entities apart:
     *
     * - users: 2 suspended (one of them also unverified) and 1 active unverified; every other row brings its own active, verified members.
     * - reports: open 1 review, 1 message, 1 list and 2 room reports, none on a profile; 2 resolved (one 10 days ago) and 2 dismissed.
     * - ratings: stars 5 now, 4 ten days ago, 2 forty days ago and 3 now on a row a moderator touched; plus a review with text that is not one.
     * - rooms: 2 public, 2 private, 2 locked; one reported on the room itself, one through a message, one with a resolved report only.
     * - lists: 2 public, 2 private; created now, 9 days ago, now and 30 days ago; one with an open report and one with a dismissed one.
     */
    protected function seedWorld(): void
    {
        User::factory()->unverified()->create();
        User::factory()->create(['is_active' => false]);
        User::factory()->unverified()->create(['is_active' => false]);

        $review = $this->review(['tmdb_id' => 1, 'is_approved' => true]);
        $this->reportOn(ReportTargetType::Review, $review);

        $this->rating(5, now());
        $this->rating(4, now()->subDays(10));
        $this->rating(2, now()->subDays(40));
        $this->rating(3, now(), ['removed_at' => now()]);

        $plain = DiscussionRoom::factory()->create();
        $reportedRoom = DiscussionRoom::factory()->private()->create();
        $messageRoom = DiscussionRoom::factory()->locked()->create();
        $closedRoom = DiscussionRoom::factory()->private()->locked()->create();
        $message = Message::factory()->create(['room_id' => $messageRoom->id]);

        $this->reportOn(ReportTargetType::Room, $reportedRoom);
        $this->reportOn(ReportTargetType::Room, $reportedRoom);
        $this->reportOn(ReportTargetType::Message, $message);
        $this->reportOn(ReportTargetType::Room, $closedRoom, 'resolved');
        $this->reportOn(ReportTargetType::Room, $plain, 'resolved', now()->subDays(10));
        $this->reportOn(ReportTargetType::Room, $plain, 'dismissed');

        $reportedList = $this->listCreatedAt(now());
        $this->listCreatedAt(now()->subDays(9));
        $this->listCreatedAt(now(), false);
        $closedList = $this->listCreatedAt(now()->subDays(30), false);

        $this->reportOn(ReportTargetType::MovieList, $reportedList);
        $this->reportOn(ReportTargetType::MovieList, $closedList, 'dismissed');
    }
}

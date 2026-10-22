<?php

namespace Tests\Feature\Auth;

use App\Auth\GuestUser;
use App\Auth\JwtCurrentUser;
use App\Contracts\CurrentUser;
use App\Models\DiscussionRoom;
use App\Models\Message;
use App\Models\MovieList;
use App\Models\MovieNight;
use App\Models\MovieNightInvite;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    private function current(User $user): CurrentUser
    {
        return new JwtCurrentUser($user, (object) ['sub' => (string) $user->id]);
    }

    private function can(CurrentUser $current, string $ability, mixed $arguments): bool
    {
        return Gate::forUser($current)->allows($ability, $arguments);
    }

    public function test_review_ownership(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $review = new Review(['user_id' => $owner->id]);

        $this->assertTrue($this->can($this->current($owner), 'update', $review));
        $this->assertFalse($this->can($this->current($other), 'update', $review));
        $this->assertFalse($this->can($this->current($admin), 'update', $review));
        $this->assertTrue($this->can($this->current($admin), 'delete', $review));
        $this->assertFalse($this->can(new GuestUser, 'delete', $review));
        $this->assertFalse($this->can(new GuestUser, 'create', Review::class));
    }

    public function test_private_movie_list_visibility(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $list = new MovieList(['user_id' => $owner->id, 'is_public' => false]);

        $this->assertTrue($this->can($this->current($owner), 'view', $list));
        $this->assertFalse($this->can($this->current($other), 'view', $list));
        $this->assertFalse($this->can(new GuestUser, 'view', $list));

        $list->is_public = true;
        $this->assertTrue($this->can(new GuestUser, 'view', $list));
        $this->assertFalse($this->can($this->current($other), 'update', $list));
    }

    public function test_private_profile_visibility(): void
    {
        $owner = User::factory()->private()->create();
        $other = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->assertTrue($this->can($this->current($owner), 'view', $owner));
        $this->assertTrue($this->can($this->current($admin), 'view', $owner));
        $this->assertFalse($this->can($this->current($other), 'view', $owner));
        $this->assertFalse($this->can(new GuestUser, 'view', $owner));
        $this->assertTrue($this->can(new GuestUser, 'view', User::factory()->create()));
    }

    public function test_movie_night_visible_to_organizer_and_invitees(): void
    {
        $organizer = User::factory()->create();
        $invitee = User::factory()->create();
        $stranger = User::factory()->create();
        $night = MovieNight::create(['organizer_id' => $organizer->id, 'title' => 'Night', 'scheduled_at' => now()->addDay()]);
        MovieNightInvite::create(['movie_night_id' => $night->id, 'user_id' => $invitee->id]);

        $this->assertTrue($this->can($this->current($organizer), 'update', $night));
        $this->assertTrue($this->can($this->current($invitee), 'view', $night));
        $this->assertFalse($this->can($this->current($invitee), 'update', $night));
        $this->assertFalse($this->can($this->current($stranger), 'view', $night));
    }

    public function test_discussion_room_messages_require_authentication_and_ownership(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $room = DiscussionRoom::create(['tmdb_id' => 603, 'name' => 'The Matrix']);
        $message = Message::create(['room_id' => $room->id, 'user_id' => $author->id, 'content' => 'Hello']);

        $this->assertFalse($this->can(new GuestUser, 'viewAny', Message::class));
        $this->assertFalse($this->can(new GuestUser, 'view', $message));
        $this->assertFalse($this->can(new GuestUser, 'create', Message::class));
        $this->assertTrue($this->can($this->current($other), 'view', $message));
        $this->assertTrue($this->can($this->current($other), 'create', Message::class));

        $this->assertTrue($this->can($this->current($author), 'update', $message));
        $this->assertFalse($this->can($this->current($other), 'update', $message));
        $this->assertFalse($this->can($this->current($admin), 'update', $message));
        $this->assertTrue($this->can($this->current($author), 'delete', $message));
        $this->assertTrue($this->can($this->current($admin), 'delete', $message));
        $this->assertFalse($this->can($this->current($other), 'delete', $message));
    }

    public function test_gate_defaults_to_the_current_user_binding(): void
    {
        $owner = User::factory()->create();
        $review = new Review(['user_id' => $owner->id]);

        $this->assertFalse(Gate::allows('update', $review));

        $this->app->instance(CurrentUser::class, $this->current($owner));
        $this->assertTrue(Gate::allows('update', $review));
    }
}

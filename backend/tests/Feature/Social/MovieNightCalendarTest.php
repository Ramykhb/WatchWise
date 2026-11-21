<?php

namespace Tests\Feature\Social;

use App\Models\MovieNight;
use App\Models\MovieNightCalendarDelivery;
use App\Models\User;
use App\MovieNight\Mail\MovieNightCalendarMail;
use App\MovieNight\Services\MovieNightCalendarService;
use App\MovieNight\Support\CalendarInvitation;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;

class MovieNightCalendarTest extends SocialTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutDefer();
        Mail::fake();
    }

    private function prepareNight(): array
    {
        [$host, $guest, $declined, $off] = User::factory()->count(4)->create()->all();
        $off->preferences()->create(['settings' => ['integrations' => ['calendar' => false]]]);
        $night = MovieNight::create(['organizer_id' => $host->id, 'title' => 'Friday films', 'scheduled_at' => now()->addDays(3), 'location' => 'At home', 'meeting_link' => 'https://example.com/meet', 'note' => 'Bring popcorn']);
        foreach ([[$guest, 'accepted'], [$declined, 'declined'], [$off, 'accepted']] as [$user, $status]) {
            $night->invites()->create(['user_id' => $user->id, 'status' => $status]);
        }
        foreach ([1003, 1008] as $id) {
            $night->candidates()->create(['tmdb_id' => $id, 'added_by' => $host->id]);
        }

        return [$night, $host, $guest, $declined, $off];
    }

    public function test_finalizing_requires_an_explicit_shortlisted_movie_even_without_votes(): void
    {
        [$night, $host] = $this->prepareNight();
        $this->as($host)->patchJson("/api/movie-nights/$night->id", ['status' => 'confirmed'])->assertUnprocessable()->assertJsonValidationErrors('winning_tmdb_id');
        $this->patchJson("/api/movie-nights/$night->id", ['status' => 'confirmed', 'winning_tmdb_id' => 999])->assertUnprocessable();
        Mail::assertNothingSent();
        $this->patchJson("/api/movie-nights/$night->id", ['status' => 'confirmed', 'winning_tmdb_id' => 1003])->assertOk()->assertJsonPath('night.winning_tmdb_id', 1003);
        Mail::assertSentCount(2);
        $this->assertDatabaseCount('movie_night_calendar_deliveries', 2);
        $this->assertSame(2, MovieNightCalendarDelivery::whereNotNull('sent_at')->count());
    }

    public function test_confirmation_is_idempotent_and_changes_send_updates_and_cancellations(): void
    {
        [$night, $host, $guest, $declined, $off] = $this->prepareNight();
        $this->as($host)->patchJson("/api/movie-nights/$night->id", ['status' => 'confirmed', 'winning_tmdb_id' => 1003])->assertOk();
        $this->patchJson("/api/movie-nights/$night->id", ['status' => 'confirmed', 'winning_tmdb_id' => 1003])->assertOk();
        Mail::assertSentCount(2);
        Mail::assertNotSent(MovieNightCalendarMail::class, fn ($mail) => $mail->hasTo($declined->email) || $mail->hasTo($off->email));
        Mail::assertSent(MovieNightCalendarMail::class, fn ($mail) => $mail->hasTo($guest->email)
            && str_contains($mail->ics, 'METHOD:REQUEST') && str_contains($mail->ics, 'SEQUENCE:1')
            && str_contains($mail->ics, 'UID:movie-night-'.$night->id.'@watchwise.app')
            && str_contains($mail->ics, 'Bring popcorn') && count($mail->attachments()) === 1);
        $this->patchJson("/api/movie-nights/$night->id", ['scheduled_at' => now()->addDays(4)->toISOString(), 'location' => 'New location'])->assertOk();
        Mail::assertSentCount(4);
        Mail::assertSent(MovieNightCalendarMail::class, fn ($mail) => str_contains($mail->ics, 'SEQUENCE:2') && str_contains($mail->ics, 'LOCATION:New location'));
        $this->patchJson("/api/movie-nights/$night->id", ['status' => 'cancelled'])->assertOk();
        Mail::assertSentCount(6);
        Mail::assertSent(MovieNightCalendarMail::class, fn ($mail) => $mail->cancelled && str_contains($mail->ics, 'METHOD:CANCEL') && str_contains($mail->ics, 'STATUS:CANCELLED') && str_contains($mail->ics, 'SEQUENCE:3'));
    }

    public function test_reopening_cancels_the_previous_calendar_event_and_clears_the_winner(): void
    {
        [$night, $host] = $this->prepareNight();
        $this->as($host)->patchJson("/api/movie-nights/$night->id", ['status' => 'confirmed', 'winning_tmdb_id' => 1003])->assertOk();
        $this->patchJson("/api/movie-nights/$night->id", ['status' => 'pending'])->assertOk()->assertJsonPath('night.winning_tmdb_id', null);
        Mail::assertSentCount(4);
        Mail::assertSent(MovieNightCalendarMail::class, fn ($mail) => $mail->cancelled);
    }

    public function test_new_guests_of_confirmed_nights_receive_the_calendar_invitation(): void
    {
        [$night, $host] = $this->prepareNight();
        $newGuest = User::factory()->create();
        $this->as($host)->patchJson("/api/movie-nights/$night->id", ['status' => 'confirmed', 'winning_tmdb_id' => 1003])->assertOk();
        $this->postJson("/api/movie-nights/$night->id/invites", ['invitees' => [$newGuest->username]])->assertOk();
        Mail::assertSentCount(3);
        Mail::assertSent(MovieNightCalendarMail::class, fn ($mail) => $mail->hasTo($newGuest->email) && ! $mail->cancelled);
    }

    public function test_shared_link_join_after_finalization_sends_a_calendar_invite_once(): void
    {
        [$night, $host] = $this->prepareNight();
        $newGuest = User::factory()->create();
        $url = $this->as($host)->postJson("/api/movie-nights/$night->id/invite-link")->assertOk()->json('url');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->patchJson("/api/movie-nights/$night->id", ['status' => 'confirmed', 'winning_tmdb_id' => 1003])->assertOk();
        $this->as($newGuest)->postJson("/api/movie-nights/$night->id/join", ['token' => $query['token']])->assertOk();
        Mail::assertSentCount(5);
        $this->postJson("/api/movie-nights/$night->id/join", ['token' => $query['token']])->assertOk();
        Mail::assertSentCount(5);
        Mail::assertSent(MovieNightCalendarMail::class, fn ($mail) => $mail->hasTo($newGuest->email) && ! $mail->cancelled);
    }

    public function test_declining_a_confirmed_night_cancels_the_guests_previous_invitation(): void
    {
        [$night, $host, $guest] = $this->prepareNight();
        $this->as($host)->patchJson("/api/movie-nights/$night->id", ['status' => 'confirmed', 'winning_tmdb_id' => 1003])->assertOk();
        $this->as($guest)->putJson("/api/movie-nights/$night->id/rsvp", ['status' => 'declined'])->assertOk();
        Mail::assertSentCount(4);
        Mail::assertSent(MovieNightCalendarMail::class, fn ($mail) => $mail->hasTo($guest->email) && $mail->cancelled);
    }

    public function test_a_failed_delivery_can_be_retried_and_a_sent_delivery_is_not_repeated(): void
    {
        [$night, $host] = $this->prepareNight();
        $night->update(['status' => 'confirmed', 'winning_tmdb_id' => 1003, 'calendar_revision' => 1]);
        $delivery = MovieNightCalendarDelivery::create(['movie_night_id' => $night->id, 'user_id' => $host->id, 'revision' => 1]);
        $mailer = Mail::getFacadeRoot();
        Mail::shouldReceive('to')->twice()->andReturnSelf();
        Mail::shouldReceive('send')->twice()->andThrow(new \RuntimeException('Mail unavailable'));
        app(MovieNightCalendarService::class)->deliver($delivery->id);
        $this->assertNull($delivery->refresh()->sent_at);
        Mail::swap($mailer);
        Mail::fake();
        $this->artisan('movie-nights:retry-calendar-mail')->assertSuccessful();
        $this->assertNotNull($delivery->refresh()->sent_at);
        app(MovieNightCalendarService::class)->deliver($delivery->id);
        Mail::assertSentCount(1);
    }

    public function test_real_mail_rendering_contains_text_html_and_a_calendar_attachment(): void
    {
        [$night, $host] = $this->prepareNight();
        $night->calendar_revision = 1;
        $ics = CalendarInvitation::render($night, $host, 'A film', false);
        $mail = new MovieNightCalendarMail($night->title, 'A film', '2030-06-20 17:30 UTC', 'Home', 'https://example.com/meet',
            'Bring popcorn', 'https://example.com/movie-nights/1', $ics, false);
        $manager = new MailManager($this->app);
        $mailer = $manager->mailer('array');
        $mailer->to($host->email)->send($mail);
        $mime = $mailer->getSymfonyTransport()->messages()->first()->getOriginalMessage()->toString();
        $this->assertStringContainsString('text/plain', $mime);
        $this->assertStringContainsString('text/html', $mime);
        $this->assertStringContainsString('text/calendar', $mime);
        $this->assertStringContainsString('movie-night.ics', $mime);
        $mail->assertSeeInText('Bring popcorn');
    }

    public function test_superseded_invites_are_skipped_and_calendar_text_cannot_inject_properties(): void
    {
        [$night, $host] = $this->prepareNight();
        $night->update(['status' => 'confirmed', 'winning_tmdb_id' => 1003, 'calendar_revision' => 2]);
        $delivery = MovieNightCalendarDelivery::create(['movie_night_id' => $night->id, 'user_id' => $host->id, 'revision' => 1]);
        app(MovieNightCalendarService::class)->deliver($delivery->id);
        $this->assertNotNull($delivery->refresh()->skipped_at);
        Mail::assertNothingSent();
        $night->title = "Movie\r\nATTENDEE:evil@example.com";
        $ics = CalendarInvitation::render($night, $host, str_repeat('🎬', 50), false);
        $this->assertStringNotContainsString("\r\nATTENDEE:evil", $ics);
        foreach (explode("\r\n", $ics) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line));
            $this->assertTrue(mb_check_encoding($line, 'UTF-8'));
        }
    }
}

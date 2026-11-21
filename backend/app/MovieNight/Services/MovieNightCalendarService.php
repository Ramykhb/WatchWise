<?php

namespace App\MovieNight\Services;

use App\Models\MovieNight;
use App\Models\MovieNightCalendarDelivery;
use App\Models\User;
use App\Movie\InterfaceServices\MovieCatalogServiceInterface;
use App\MovieNight\Mail\MovieNightCalendarMail;
use App\MovieNight\Support\CalendarInvitation;
use App\Support\Exceptions\MailNotDelivered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** A small durable outbox: send after commit/response; failed messages remain retryable. */
class MovieNightCalendarService
{
    public function __construct(private readonly MovieCatalogServiceInterface $catalog) {}

    public function schedule(MovieNight $night, ?array $onlyUserIds = null, bool $cancelled = false): void
    {
        $ids = [$night->organizer_id, ...$night->invites()->where('status', '!=', 'declined')->pluck('user_id')->all()];
        if ($cancelled) {
            $ids = MovieNightCalendarDelivery::where('movie_night_id', $night->id)->where('cancelled', false)->whereNotNull('sent_at')->pluck('user_id')->all();
        }
        if ($onlyUserIds !== null) {
            $ids = array_intersect($ids, $onlyUserIds);
        }
        foreach (User::with('preferences')->whereIn('id', array_unique($ids))->where('is_active', true)->whereNotNull('email_verified_at')->get() as $user) {
            if (! $cancelled && ($user->preferences?->settings['integrations']['calendar'] ?? true) === false) {
                continue;
            }
            $delivery = MovieNightCalendarDelivery::firstOrCreate(['movie_night_id' => $night->id, 'user_id' => $user->id, 'revision' => $night->calendar_revision], ['cancelled' => $cancelled]);
            if ($delivery->sent_at === null && $delivery->skipped_at === null) {
                DB::afterCommit(fn () => defer(fn () => $this->deliver($delivery->id)));
            }
        }
    }

    public function retryPending(): int
    {
        $ids = MovieNightCalendarDelivery::whereNull('sent_at')->whereNull('skipped_at')->oldest('id')->limit(100)->pluck('id');
        foreach ($ids as $id) {
            $this->deliver($id);
        }

        return $ids->count();
    }

    public function deliver(int $id): void
    {
        try {
            DB::transaction(function () use ($id): void {
                $delivery = MovieNightCalendarDelivery::whereKey($id)->lockForUpdate()->first();
                if ($delivery === null || $delivery->sent_at !== null || $delivery->skipped_at !== null) {
                    return;
                }
                $night = MovieNight::find($delivery->movie_night_id);
                $user = User::with('preferences')->find($delivery->user_id);
                if ($night === null || $user === null || ! $user->is_active || $night->calendar_revision !== $delivery->revision
                    || (! $delivery->cancelled && ($night->status !== 'confirmed' || ($user->preferences?->settings['integrations']['calendar'] ?? true) === false))) {
                    $delivery->update(['skipped_at' => now()]);

                    return;
                }
                // Remember once, then reuse the local title for the mail and all later recipients.
                if ($night->winning_tmdb_id !== null) {
                    $this->catalog->remember($night->winning_tmdb_id);
                }
                $movieTitle = $night->winning_tmdb_id === null ? 'Movie selection pending'
                    : ($this->catalog->summaries([$night->winning_tmdb_id])?->get($night->winning_tmdb_id)?->title ?? 'Movie #'.$night->winning_tmdb_id);
                $mail = new MovieNightCalendarMail($night->title, $movieTitle, $night->scheduled_at->copy()->utc()->format('Y-m-d H:i').' UTC',
                    $night->location ?? $night->meeting_link ?? 'To be announced', $night->meeting_link, $night->note,
                    rtrim(config('services.frontend_url'), '/').'/movie-nights/'.$night->id,
                    CalendarInvitation::render($night, $user, $movieTitle, $delivery->cancelled), $delivery->cancelled);
                retry([1000], fn () => Mail::to($user->email)->send($mail));
                $delivery->update(['sent_at' => now()]);
            });
        } catch (Throwable $failure) {
            report(MailNotDelivered::after(2, MovieNightCalendarMail::class, $failure));
        }
    }
}

<?php

namespace App\Digest\Services;

use App\Digest\Mail\WeeklyDigestMail;
use App\Models\User;
use App\Models\WeeklyDigestDelivery;
use App\Movie\DTOs\Requests\MovieTrendingQuery;
use App\Movie\InterfaceServices\MovieServiceInterface;
use App\Support\Exceptions\MailNotDelivered;
use App\User\InterfaceServices\HomeServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** One opt-in digest per UTC week. Failed sends remain pending until the next hourly run. */
class WeeklyDigestService
{
    public function __construct(private readonly MovieServiceInterface $movies, private readonly HomeServiceInterface $home) {}

    public function sendDue(): int
    {
        $now = CarbonImmutable::now('UTC');
        $week = $now->startOfWeek();
        if ($now->lt($week->addHours(9))) {
            return 0;
        }
        $sent = 0;
        User::with('preferences')->where('is_active', true)->whereNotNull('email_verified_at')
            ->whereHas('preferences', fn ($query) => $query->where('settings->integrations->digest', true))
            ->chunkById(100, function ($users) use ($week, &$sent): void {
                foreach ($users as $user) {
                    $delivery = WeeklyDigestDelivery::firstOrCreate(['user_id' => $user->id, 'week' => $week->toDateString()]);
                    if ($this->deliver($delivery->id)) {
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    public function deliver(int $id): bool
    {
        try {
            return DB::transaction(function () use ($id): bool {
                $delivery = WeeklyDigestDelivery::whereKey($id)->lockForUpdate()->first();
                if ($delivery === null || $delivery->sent_at || $delivery->skipped_at) {
                    return false;
                }
                $user = User::with('preferences')->find($delivery->user_id);
                if ($user === null || ! $user->is_active || ! $user->email_verified_at
                    || ($user->preferences?->settings['integrations']['digest'] ?? false) !== true
                    || $delivery->week !== CarbonImmutable::now('UTC')->startOfWeek()->toDateString()) {
                    $delivery->update(['skipped_at' => now()]);

                    return false;
                }
                $base = rtrim(config('services.frontend_url'), '/');
                $picks = collect($this->movies->trending(new MovieTrendingQuery)->movies)->take(5)
                    ->map(fn ($movie): array => ['title' => $movie->title, 'url' => $base.'/movies/'.$movie->id])->all();
                // Reuse the home feed's live privacy, moderation and hidden-title checks. Never email spoilers.
                $activity = collect($this->home->activity($user))
                    ->filter(fn (array $item): bool => CarbonImmutable::parse($item['created_at'])->betweenIncluded(CarbonImmutable::now('UTC')->subDays(7), CarbonImmutable::now('UTC')))
                    ->map(function (array $item) use ($base): array {
                        $isList = $item['kind'] === 'list';
                        $title = ($item['media_type'] ?? 'movie') === 'tv' ? 'a TV show' : 'a movie';
                        $action = match ($item['kind']) {
                            'reviewed' => 'reviewed '.$title,
                            'rated' => 'rated '.$title,
                            'watched' => 'watched '.$title,
                            'watchlist' => 'added '.$title.' to their watchlist',
                            'list' => 'updated their list: '.$item['list']['title'],
                        };

                        return ['name' => $item['user']['name'], 'action' => $action,
                            'rating' => $item['rating'] ?? null,
                            'excerpt' => ($item['has_spoilers'] ?? false) ? null : ($item['excerpt'] ?? null),
                            'url' => $isList ? $base.'/lists/'.$item['list']['id']
                                : $base.'/movies/'.(($item['media_type'] ?? 'movie') === 'tv' ? 'tv-' : '').$item['tmdb_id']];
                    })->values()->all();
                Mail::to($user->email)->send(new WeeklyDigestMail($picks, $activity, $base.'/home-page', $base.'/settings'));
                $delivery->update(['sent_at' => now(), 'attempts' => $delivery->attempts + 1]);

                return true;
            });
        } catch (Throwable $failure) {
            WeeklyDigestDelivery::whereKey($id)->increment('attempts');
            report(MailNotDelivered::after(1, WeeklyDigestMail::class, $failure));

            return false;
        }
    }
}

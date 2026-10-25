<?php

namespace Database\Seeders;

use App\Models\DiscussionRoom;
use App\Models\MovieList;
use App\Models\MovieNight;
use App\Models\Review;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo members who follow each other, with lists, a watchlist, reviews, rooms, movie nights and
 * notifications, so every signed-in page has real rows to show. Sign in as maya@watchwise.test
 * (or any other demo email below) with the password "watchwise1".
 *
 * Films are TMDB ids. Until the TMDB integration lands the frontend maps 1001–1008 to its eight
 * demo movies (frontend/src/lib/movies.ts); any other id shows a placeholder poster.
 */
class DemoSocialSeeder extends Seeder
{
    private const NEON_TIDE = 1001;

    private const STARDUST = 1002;

    private const DEEP_BLUE = 1003;

    private const LAST_LAP = 1004;

    private const VELVET_HOUR = 1005;

    private const WILD_FERN = 1006;

    private const PAPER_MOON = 1007;

    private const RED_SIGNAL = 1008;

    public function run(): void
    {
        $password = Hash::make('watchwise1');
        $member = function (string $username, string $name, string $bio, string $location, string $since) use ($password) {
            $user = User::firstOrNew(['email' => "{$username}@watchwise.test"]);
            $user->forceFill([
                'name' => $name,
                'username' => $username,
                'password' => $password,
                'bio' => $bio,
                'location' => $location,
                'email_verified_at' => now(),
                'created_at' => $since,
            ])->save();

            return $user;
        };

        $maya = $member('maya', 'Maya Rivera', 'Sci-fi devotee. Will cry at any ocean movie.', 'Beirut', '2024-02-10');
        $lina = $member('linasaad', 'Lina Saad', "Slow cinema evangelist, rainy-day list maker and professional popcorn critic. If it has a melancholy soundtrack, I've probably reviewed it.", 'Beirut', '2023-05-02');
        $omar = $member('omark', 'Omar Khalil', 'Thrillers, heists and anything under 90 minutes.', 'Tripoli', '2023-11-19');
        $jad = $member('jadn', 'Jad Nassar', 'Ending-explainer in chief. Spoilers in the rooms, never in reviews.', 'Byblos', '2024-01-08');
        $rana = $member('ranah', 'Rana Haddad', 'Awards-season obsessive and Oscars pool champion.', 'Achrafieh', '2023-09-30');
        $sam = $member('samt', 'Sam Tannous', 'Horror Night Crew founder. Lights off, volume up.', 'Jounieh', '2024-06-14');

        $this->preferences($maya, ['Sci-fi', 'Drama', 'Adventure']);
        $this->preferences($lina, ['Drama', 'Romance', 'Mystery', 'Slow cinema', 'Documentary']);
        $this->preferences($omar, ['Thriller', 'Action', 'Crime']);
        $this->preferences($jad, ['Sci-fi', 'Fantasy']);
        $this->preferences($rana, ['Drama', 'Comedy']);
        $this->preferences($sam, ['Horror', 'Thriller']);

        // Who follows whom.
        $follows = [
            [$maya, [$lina, $omar]],
            [$lina, [$maya, $omar, $jad, $rana]],
            [$omar, [$maya, $lina]],
            [$jad, [$maya, $lina, $rana]],
            [$rana, [$lina, $jad]],
            [$sam, [$omar, $jad]],
        ];
        foreach ($follows as [$follower, $followed]) {
            $follower->following()->syncWithoutDetaching(collect($followed)->pluck('id')->all());
        }

        // Lists.
        $list = function (User $owner, string $title, string $description, bool $public, array $films) {
            $list = MovieList::firstOrCreate(['user_id' => $owner->id, 'title' => $title], ['description' => $description, 'is_public' => $public]);
            foreach ($films as $position => $tmdbId) {
                $list->items()->updateOrCreate(['tmdb_id' => $tmdbId], ['position' => $position]);
            }

            return $list;
        };
        $list($maya, 'Favorite Comedies', 'Guaranteed laughs for bad days and good friends.', true, [self::PAPER_MOON, self::VELVET_HOUR, self::NEON_TIDE]);
        $list($maya, 'Movies to Watch This Summer', 'Big, bright adventures for long warm nights.', true, [self::WILD_FERN, self::DEEP_BLUE, self::PAPER_MOON, self::RED_SIGNAL]);
        $list($maya, 'Comfort Rewatches', 'My personal ‘reset button’ films.', false, [self::STARDUST, self::LAST_LAP, self::VELVET_HOUR]);
        $list($lina, 'Rainy Day Comfort', 'Soft, slow, atmospheric films for grey afternoons — the cinematic equivalent of a blanket and a hot drink.', true, [self::DEEP_BLUE, self::VELVET_HOUR, self::STARDUST, self::LAST_LAP, self::PAPER_MOON, self::WILD_FERN]);
        $list($jad, 'Sci-fi That Feels Like a Hug', 'Big ideas, warm hearts.', true, [self::NEON_TIDE, self::STARDUST, self::LAST_LAP]);
        $list($omar, 'Under 90 Minutes', 'Tight, lean, no filler.', true, [self::RED_SIGNAL, self::PAPER_MOON, self::WILD_FERN]);

        // Watchlists and watch history.
        $watch = function (User $user, int $tmdbId, string $status, ?string $watchedAt = null) {
            $user->watchlists()->updateOrCreate(['tmdb_id' => $tmdbId], ['status' => $status, 'watched_at' => $watchedAt]);
        };
        foreach ([self::DEEP_BLUE, self::STARDUST, self::VELVET_HOUR, self::RED_SIGNAL] as $tmdbId) {
            $watch($maya, $tmdbId, 'to_watch');
        }
        $watch($maya, self::NEON_TIDE, 'watched', now()->subDays(3));
        $watch($maya, self::PAPER_MOON, 'watched', now()->subDays(6));
        $watch($maya, self::LAST_LAP, 'watched', now()->subDays(10));
        $watch($maya, self::WILD_FERN, 'watched', now()->subDays(25));
        foreach ([self::DEEP_BLUE, self::VELVET_HOUR, self::STARDUST, self::WILD_FERN, self::LAST_LAP, self::PAPER_MOON] as $i => $tmdbId) {
            $watch($lina, $tmdbId, 'watched', now()->subDays(2 + $i * 4));
        }
        $watch($lina, self::NEON_TIDE, 'to_watch');
        $watch($rana, self::STARDUST, 'to_watch');

        // Reviews (approved so they show on movie pages later).
        $review = function (User $user, int $tmdbId, int $rating, string $content, string $sentiment = 'positive') {
            Review::firstOrCreate(
                ['user_id' => $user->id, 'tmdb_id' => $tmdbId],
                ['rating' => $rating, 'content' => $content, 'sentiment' => $sentiment, 'is_approved' => true],
            );
        };
        $review($maya, self::NEON_TIDE, 5, 'The ocean scenes alone are worth it. I cried twice and would happily cry again.');
        $review($maya, self::PAPER_MOON, 4, 'Light, quick and genuinely funny — the perfect Friday film.');
        $review($lina, self::VELVET_HOUR, 5, 'Warm, gentle and quietly devastating. The last ten minutes wrecked me in the best possible way.');
        $review($lina, self::DEEP_BLUE, 5, 'Every frame is drenched in blue and dread. Tense, patient and beautifully scored.');
        $review($omar, self::PAPER_MOON, 5, 'Best comedy of the year, no contest.');
        $review($jad, self::DEEP_BLUE, 4, 'Tense, gorgeous, unforgettable.');
        $review($rana, self::RED_SIGNAL, 2, 'Loud and hollow. Great poster though.', 'negative');

        // Rooms and messages.
        $room = function (string $name, int $tmdbId, User $host, bool $spoilers, array $messages) {
            $room = DiscussionRoom::firstOrCreate(['name' => $name], ['tmdb_id' => $tmdbId, 'created_by' => $host->id, 'spoilers' => $spoilers]);
            if ($room->messages()->doesntExist()) {
                foreach ($messages as $i => [$author, $content]) {
                    $room->messages()->make(['user_id' => $author->id, 'content' => $content])
                        ->forceFill(['created_at' => now()->subMinutes((count($messages) - $i) * 4)])->save();
                }
            }

            return $room;
        };
        $room('Neon Tide — ending explained', self::NEON_TIDE, $jad, true, [
            [$jad, 'Okay so the lighthouse scene — was she actually back in 2019 or is the current just replaying the memory?'],
            [$lina, "Replaying! The colour grading switches to the cooler palette every time it's a memory."],
            [$maya, 'YES. And the final shot is warm again, which means she chose the present.'],
            [$omar, "Just finished it. Not crying, you're crying."],
        ]);
        $room('Thriller Thursday', self::DEEP_BLUE, $omar, false, [
            [$lina, 'Who is still up for a thriller this week?'],
            [$sam, 'Always. Something slow-burn please.'],
            [$omar, 'Deep Blue tonight?'],
        ]);
        $room('Oscars 2026 picks', self::PAPER_MOON, $rana, false, [
            [$jad, 'Paper Moon for Best Picture, fight me.'],
            [$rana, 'Best Director is locked. Nobody else came close this year.'],
        ]);
        $room('Horror Night Crew', self::RED_SIGNAL, $sam, false, [
            [$sam, "Who's in for Friday? Lina is hosting Friday Frights."],
        ]);

        // Movie nights: one Lina hosts (Maya going), one Omar invited Maya to (pending), one Maya hosts.
        if (MovieNight::where('title', 'Friday Frights')->doesntExist()) {
            $frights = MovieNight::create(['organizer_id' => $lina->id, 'title' => 'Friday Frights', 'scheduled_at' => now()->addDays(4)->setTime(20, 30), 'location' => "Lina's place, Hamra", 'note' => 'Bring snacks — popcorn is on me.']);
            foreach ([[$maya, 'accepted'], [$omar, 'accepted'], [$jad, 'maybe'], [$sam, 'pending']] as [$guest, $status]) {
                $frights->invites()->create(['user_id' => $guest->id, 'status' => $status]);
            }
            foreach ([[self::DEEP_BLUE, $lina], [self::RED_SIGNAL, $omar], [self::NEON_TIDE, $maya]] as [$tmdbId, $by]) {
                $frights->candidates()->create(['tmdb_id' => $tmdbId, 'added_by' => $by->id]);
            }
            foreach ([[$lina, self::DEEP_BLUE], [$omar, self::DEEP_BLUE], [$maya, self::NEON_TIDE]] as [$voter, $tmdbId]) {
                $frights->votes()->create(['user_id' => $voter->id, 'tmdb_id' => $tmdbId]);
            }

            $scifi = MovieNight::create(['organizer_id' => $omar->id, 'title' => 'Sci-fi Saturday', 'scheduled_at' => now()->addDays(5)->setTime(21, 0), 'meeting_link' => 'https://meet.watchwise.app/scifi']);
            $scifi->candidates()->create(['tmdb_id' => self::NEON_TIDE, 'added_by' => $omar->id]);
            $scifi->candidates()->create(['tmdb_id' => self::STARDUST, 'added_by' => $omar->id]);
            foreach ([$maya, $jad] as $guest) {
                $scifi->invites()->create(['user_id' => $guest->id]);
            }

            $comedy = MovieNight::create(['organizer_id' => $maya->id, 'title' => 'Comedy Night', 'scheduled_at' => now()->addDays(12)->setTime(21, 0), 'meeting_link' => 'https://meet.watchwise.app/comedy']);
            $comedy->candidates()->create(['tmdb_id' => self::PAPER_MOON, 'added_by' => $maya->id]);
            $comedy->candidates()->create(['tmdb_id' => self::WILD_FERN, 'added_by' => $maya->id]);
            foreach ([[$jad, 'accepted'], [$rana, 'accepted']] as [$guest, $status]) {
                $comedy->invites()->create(['user_id' => $guest->id, 'status' => $status]);
            }
            $comedy->votes()->create(['user_id' => $jad->id, 'tmdb_id' => self::PAPER_MOON]);
        }
    }

    /**
     * @param  array<string>  $genres
     */
    private function preferences(User $user, array $genres): void
    {
        UserPreference::updateOrCreate(['user_id' => $user->id], ['favorite_genres' => $genres]);
    }
}

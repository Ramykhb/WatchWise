<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    protected $hidden = ['password', 'remember_token'];

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'avatar',
        'bio',
        'location',
        'profile_visibility',
    ];

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /** Every account gets a unique @handle; sign-up and social sign-in only send a name. */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->username ??= static::uniqueUsername($user->name ?: Str::before((string) $user->email, '@'));
        });
    }

    public static function uniqueUsername(string $from): string
    {
        $base = Str::of($from)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '')->limit(20, '')->toString() ?: 'member';
        $username = $base;
        for ($n = 2; static::where('username', $username)->exists(); $n++) {
            $username = $base.$n;
        }

        return $username;
    }

    /** A privacy toggle from Settings (ratings, reviews, lists, watchlist, history, following). */
    public function shares(string $key): bool
    {
        $defaults = ['ratings' => true, 'reviews' => true, 'lists' => true, 'watchlist' => false, 'history' => true, 'following' => true];

        return (bool) ($this->preferences?->settings['privacy'][$key] ?? $defaults[$key] ?? true);
    }

    /** "followers" profiles are visible to people who follow the owner. */
    public function isFollowedBy(?int $userId): bool
    {
        return $userId !== null && $this->followers()->whereKey($userId)->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function socialAccounts()
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function watchlists()
    {
        return $this->hasMany(Watchlist::class);
    }

    public function preferences()
    {
        return $this->hasOne(UserPreference::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function movieLists()
    {
        return $this->hasMany(MovieList::class);
    }

    public function following()
    {
        return $this->belongsToMany(self::class, 'follows', 'follower_id', 'following_id')
            ->withPivot('id');
    }

    public function followers()
    {
        return $this->belongsToMany(self::class, 'follows', 'following_id', 'follower_id')
            ->withPivot('id');
    }

    public function activityFeed()
    {
        return $this->hasMany(ActivityFeed::class);
    }

    public function organizedMovieNights()
    {
        return $this->hasMany(MovieNight::class, 'organizer_id');
    }

    public function movieNightInvites()
    {
        return $this->hasMany(MovieNightInvite::class);
    }

    public function addedMovieNightCandidates()
    {
        return $this->hasMany(MovieNightCandidate::class, 'added_by');
    }

    public function movieNightVotes()
    {
        return $this->hasMany(MovieNightVote::class);
    }

    public function calendarEvents()
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
}

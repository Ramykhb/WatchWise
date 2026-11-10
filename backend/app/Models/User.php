<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    protected $hidden = ['password', 'remember_token', 'last_active_at', 'pending_email', 'pending_email_nonce', 'pending_email_authorized_at', 'pending_email_link_sent_at', 'password_set_without_proof_at', 'deletion_requested_at', 'deletion_nonce'];

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

        // Any new password (Settings, reset by email, social takeover) drops a pending account deletion. Its
        // links are bound to the password hash and stop working anyway; this also clears it from Settings.
        static::saving(function (User $user) {
            if ($user->exists && $user->isDirty('password') && $user->deletion_nonce !== null) {
                $user->forceFill(['deletion_requested_at' => null, 'deletion_nonce' => null]);
            }
        });
    }

    /** Handles are at most 20 characters (Settings validates the same), suffix included. */
    public const USERNAME_MAX = 20;

    public static function uniqueUsername(string $from): string
    {
        $base = Str::of($from)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '')->limit(self::USERNAME_MAX, '')->toString() ?: 'member';
        $username = $base;
        for ($n = 2; static::where('username', $username)->exists(); $n++) {
            // Shorten the base so "name" + number still fits.
            $username = substr($base, 0, self::USERNAME_MAX - strlen((string) $n)).$n;
        }

        return $username;
    }

    /**
     * The column holds either a path on the public disk (a picture uploaded here, e.g. "avatars/x.png")
     * or an external URL copied from Google, GitHub or Discord. Reading it always yields a full URL, so
     * the stored value never bakes in this server's address.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function avatar(): Attribute
    {
        return Attribute::get(fn (?string $stored): ?string => match (true) {
            $stored === null || $stored === '' => null,
            $this->isExternalAvatar($stored) => $stored,
            default => Storage::disk('public')->url($stored),
        });
    }

    /** The path of the uploaded picture on the public disk, or null when there is none (or it is external). */
    public function storedAvatarPath(): ?string
    {
        $stored = $this->getRawOriginal('avatar');

        return is_string($stored) && $stored !== '' && ! $this->isExternalAvatar($stored) ? $stored : null;
    }

    private function isExternalAvatar(string $stored): bool
    {
        return str_starts_with($stored, 'http://') || str_starts_with($stored, 'https://');
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
            'last_active_at' => 'datetime',
            'pending_email_authorized_at' => 'datetime',
            'pending_email_link_sent_at' => 'datetime',
            'password_set_without_proof_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
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

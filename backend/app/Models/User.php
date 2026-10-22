<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $hidden = ['password', 'remember_token'];

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'bio',
        'profile_visibility',
    ];

    /** @use HasFactory<UserFactory> */
    use HasFactory;

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

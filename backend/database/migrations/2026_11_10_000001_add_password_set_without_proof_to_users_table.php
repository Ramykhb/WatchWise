<?php

use App\Support\SocialPlaceholderPassword;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const VERIFIED_AT_CREATION_SECONDS = 300;

    /**
     * Set when an account made by social sign-in chooses its first password in Settings, where no
     * current password can be asked for. Until the owner proves the inbox (a password reset through the
     * emailed link, or a completed email change), that password does not count as proof for an email
     * change, so the current address still has to approve it.
     *
     * Existing accounts are backfilled, failing closed, since there is no record of how their password
     * was set. Stamped: every user whose stored password is not the placeholder and who either has a
     * linked social account, or has none (it may have been disconnected) but was verified within a few
     * minutes of being created, as social sign-in accounts are (email sign-ups verify later through the
     * emailed link). Accounts still on the placeholder stay null (they are already treated as
     * passwordless). When SOCIAL_PLACEHOLDER_PASSWORD is not configured the placeholder check cannot
     * tell, so those users are stamped either way. It costs one current-inbox approval, or a reset.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_set_without_proof_at')->nullable()->after('password');
        });

        $placeholder = SocialPlaceholderPassword::value();
        $now = now();

        DB::table('users')
            ->select(['id', 'password', 'created_at', 'email_verified_at'])
            ->selectSub(fn (Builder $query) => $query->selectRaw('count(*)')->from('social_accounts')->whereColumn('social_accounts.user_id', 'users.id'), 'social_accounts_count')
            ->chunkById(500, function (Collection $users) use ($placeholder, $now): void {
                $ids = $users
                    ->filter(fn (object $user) => $user->social_accounts_count > 0 || $this->verifiedAtCreation($user))
                    ->reject(fn (object $user) => $placeholder !== null && $this->isPlaceholder($placeholder, (string) $user->password))
                    ->pluck('id');

                if ($ids->isNotEmpty()) {
                    DB::table('users')->whereIn('id', $ids)->update(['password_set_without_proof_at' => $now]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_set_without_proof_at');
        });
    }

    /** Social sign-in marks the email verified when it creates the account; email sign-ups verify later. */
    private function verifiedAtCreation(object $user): bool
    {
        if ($user->created_at === null || $user->email_verified_at === null) {
            return false;
        }

        return abs(Carbon::parse($user->email_verified_at)->diffInSeconds(Carbon::parse($user->created_at))) <= self::VERIFIED_AT_CREATION_SECONDS;
    }

    /** Same check as SocialPlaceholderPassword::isStoredFor(); an unreadable hash counts as a real password. */
    private function isPlaceholder(string $placeholder, string $hash): bool
    {
        try {
            return Hash::check($placeholder, $hash);
        } catch (RuntimeException) {
            return false;
        }
    }
};

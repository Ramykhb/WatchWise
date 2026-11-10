<?php

use App\Support\SocialPlaceholderPassword;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Set when an account made by social sign-in chooses its first password in Settings, where no
     * current password can be asked for. Until the owner proves the inbox (a password reset through the
     * emailed link, or a completed email change), that password does not count as proof for an email
     * change, so the current address still has to approve it.
     *
     * Existing accounts are backfilled, failing closed: every user with a linked social account whose
     * stored password is not the placeholder is stamped, since there is no record of how that password
     * was set. Accounts still on the placeholder stay null (they are already treated as passwordless).
     * When SOCIAL_PLACEHOLDER_PASSWORD is not configured the check cannot tell, so every user with a
     * social account is stamped. Either way it costs one current-inbox approval, or a password reset.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_set_without_proof_at')->nullable()->after('password');
        });

        $placeholder = SocialPlaceholderPassword::value();
        $now = now();

        DB::table('users')
            ->select(['id', 'password'])
            ->whereExists(fn (Builder $query) => $query->select(DB::raw(1))->from('social_accounts')->whereColumn('social_accounts.user_id', 'users.id'))
            ->chunkById(500, function (Collection $users) use ($placeholder, $now): void {
                $ids = $users
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

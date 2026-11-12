<?php

use Dotenv\Dotenv;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    private const PLACEHOLDER_KEY = 'SOCIAL_PLACEHOLDER_PASSWORD';

    private const SKIP_KEY = 'SKIP_SOCIAL_PLACEHOLDER_CLEANUP';

    /**
     * Social sign-ups used to get a shared placeholder password (SOCIAL_PLACEHOLDER_PASSWORD) so the app could
     * tell they never chose one; they now store NULL instead. This turns every account whose stored hash still
     * matches the placeholder into a NULL-password account.
     *
     * Run it while SOCIAL_PLACEHOLDER_PASSWORD is still set, as a process environment variable or in `.env`
     * (the file is parsed directly too, so a cached config does not hide it; the app has no config key for it
     * any more). It never skips silently: when no placeholder is found while social accounts with a password
     * exist, it fails and asks for one. Set SKIP_SOCIAL_PLACEHOLDER_CLEANUP=true instead when this environment
     * never used a placeholder. Social accounts that were given a random password are left alone: they use
     * "Forgot password".
     */
    public function up(): void
    {
        $envFile = base_path('.env');
        $skip = $this->readSetting(self::SKIP_KEY, env(self::SKIP_KEY), $envFile);

        $this->clearPlaceholderPasswords(
            $this->readSetting(self::PLACEHOLDER_KEY, env(self::PLACEHOLDER_KEY), $envFile),
            filter_var($skip, FILTER_VALIDATE_BOOLEAN),
        );
    }

    /**
     * Reverse the migrations. Nothing to undo: the placeholder hashes are not restored, and NULL already
     * means "no password of its own", which is what they stood for.
     */
    public function down(): void
    {
        //
    }

    /**
     * A setting from the process environment, or else from the `.env` file (quoted values handled by Dotenv).
     * Empty counts as unset.
     */
    public function readSetting(string $key, mixed $fromEnvironment, string $envFile): ?string
    {
        if (is_bool($fromEnvironment)) {
            return $fromEnvironment ? 'true' : 'false';
        }
        if (is_string($fromEnvironment) && $fromEnvironment !== '') {
            return $fromEnvironment;
        }
        if (! is_file($envFile)) {
            return null;
        }

        $value = Dotenv::parse((string) file_get_contents($envFile))[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * The body of up() with its settings passed in, so tests need no environment variable. Returns how many
     * accounts were cleared.
     *
     * A NULL password also means no password set without proof. The pending email change and account deletion
     * are dropped too: their links are bound to the old password hash, so they stopped working anyway.
     *
     * @throws RuntimeException when no placeholder is known but social accounts with a password exist
     */
    public function clearPlaceholderPasswords(?string $placeholder, bool $skip = false): int
    {
        if ($skip) {
            return 0;
        }

        if ($placeholder === null || $placeholder === '') {
            if ($this->socialAccountsWithAPasswordExist()) {
                throw new RuntimeException(
                    'Migration clear_social_placeholder_passwords needs the old SOCIAL_PLACEHOLDER_PASSWORD to find the accounts still holding it. '
                    .'Set it (as a process environment variable or in .env) and run the migration again, '
                    .'or set SKIP_SOCIAL_PLACEHOLDER_CLEANUP=true if this environment never used a placeholder.'
                );
            }

            return 0;
        }

        $cleared = 0;

        DB::table('users')
            ->select(['id', 'password'])
            ->whereNotNull('password')
            ->chunkById(200, function (Collection $users) use ($placeholder, &$cleared): void {
                $ids = $users->filter(fn (object $user) => $this->isPlaceholder($placeholder, (string) $user->password))->pluck('id');

                if ($ids->isNotEmpty()) {
                    $cleared += DB::table('users')->whereIn('id', $ids)->update([
                        'password' => null,
                        'password_set_without_proof_at' => null,
                        'pending_email' => null,
                        'pending_email_nonce' => null,
                        'pending_email_authorized_at' => null,
                        'deletion_requested_at' => null,
                        'deletion_nonce' => null,
                    ]);
                }
            });

        return $cleared;
    }

    private function socialAccountsWithAPasswordExist(): bool
    {
        return DB::table('users')
            ->whereNotNull('password')
            ->whereExists(fn (Builder $query) => $query->selectRaw('1')->from('social_accounts')->whereColumn('social_accounts.user_id', 'users.id'))
            ->exists();
    }

    /** An unreadable hash counts as a real password. */
    private function isPlaceholder(string $placeholder, string $hash): bool
    {
        try {
            return Hash::check($placeholder, $hash);
        } catch (RuntimeException) {
            return false;
        }
    }
};

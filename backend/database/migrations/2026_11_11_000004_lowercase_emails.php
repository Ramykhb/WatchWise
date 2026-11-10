<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    private const CHUNK = 500;

    /**
     * Emails are now stored lowercase and trimmed. This lowercases `users.email`, `users.pending_email` and
     * `password_resets.email` (the only other table holding addresses for lookup).
     *
     * It checks first and changes nothing when two accounts' emails are the same once lowercased ("Ali@x.com"
     * and "ali@x.com"): a person has to decide which account keeps the address, so the migration fails with
     * the ids and emails involved. A pending email change to an address another account uses (in any case)
     * could never be applied, so that change is cleared instead and the migration carries on.
     */
    public function up(): void
    {
        $cleared = $this->lowercaseEmails();

        if ($cleared > 0) {
            Log::info("Migration lowercase_emails cleared {$cleared} pending email change(s) to an address another account uses.");
        }
    }

    /**
     * Reverse the migrations. The original casing is not kept, and lowercase addresses work the same way,
     * so there is nothing to restore.
     */
    public function down(): void
    {
        //
    }

    /**
     * The body of up(), public so tests can run it on the data they create. Returns how many pending email
     * changes were cleared because another account uses their address.
     *
     * @throws RuntimeException when lowercasing would make two accounts share an address (nothing is changed)
     */
    public function lowercaseEmails(): int
    {
        $collisions = $this->emailCollisions();

        if ($collisions !== []) {
            throw new RuntimeException(
                "Migration lowercase_emails stopped without changing anything: these accounts' emails are the same once lowercased.\n"
                .implode("\n", $collisions)."\n"
                .'Resolve them manually (change or remove one address in each group), then run the migration again.'
            );
        }

        return DB::transaction(function (): int {
            $cleared = $this->clearPendingEmailsTakenByAnotherAccount();
            $this->lowercaseColumns('users', ['email', 'pending_email']);
            $this->lowercaseColumns('password_resets', ['email']);

            return $cleared;
        });
    }

    /**
     * One line per group of accounts sharing an address once lowercased, naming the user ids and stored emails.
     *
     * @return list<string>
     */
    private function emailCollisions(): array
    {
        $lines = [];

        $sharedAddresses = DB::table('users')
            ->selectRaw('LOWER(TRIM(email)) AS address')
            ->groupByRaw('LOWER(TRIM(email))')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('address');

        foreach ($sharedAddresses->chunk(self::CHUNK) as $addresses) {
            DB::table('users')
                ->whereIn(DB::raw('LOWER(TRIM(email))'), $addresses->values()->all())
                ->orderBy('id')
                ->get(['id', 'email', DB::raw('LOWER(TRIM(email)) AS address')])
                ->groupBy('address')
                ->each(function (Collection $users) use (&$lines): void {
                    $lines[] = 'email: '.$users->map(fn (object $user): string => "user {$user->id} <{$user->email}>")->implode(', ');
                });
        }

        return $lines;
    }

    /**
     * Clears every pending email change whose address another account already uses, exactly or in another
     * case: the change could never be applied. The ids are read first because MySQL cannot update a table
     * that its own subquery reads.
     */
    private function clearPendingEmailsTakenByAnotherAccount(): int
    {
        $ids = DB::table('users AS pending')
            ->join('users AS owner', function (JoinClause $join): void {
                $join->on(DB::raw('LOWER(TRIM(owner.email))'), '=', DB::raw('LOWER(TRIM(pending.pending_email))'))
                    ->on('owner.id', '!=', 'pending.id');
            })
            ->whereNotNull('pending.pending_email')
            ->distinct()
            ->pluck('pending.id');

        $cleared = 0;

        foreach ($ids->chunk(self::CHUNK) as $chunk) {
            $cleared += DB::table('users')->whereIn('id', $chunk->values()->all())->update([
                'pending_email' => null,
                'pending_email_nonce' => null,
                'pending_email_authorized_at' => null,
                'pending_email_link_sent_at' => null,
            ]);
        }

        return $cleared;
    }

    /**
     * @param  list<string>  $columns
     */
    private function lowercaseColumns(string $table, array $columns): void
    {
        $lowered = collect($columns)->mapWithKeys(fn (string $column) => [$column => DB::raw("LOWER(TRIM({$column}))")])->all();

        DB::table($table)->select('id')->chunkById(self::CHUNK, function (Collection $rows) use ($table, $lowered): void {
            DB::table($table)->whereIn('id', $rows->pluck('id'))->update($lowered);
        });
    }
};

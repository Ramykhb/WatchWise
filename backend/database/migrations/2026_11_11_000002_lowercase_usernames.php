<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const USERNAME_MAX = 20;

    /**
     * Handles are now stored lowercase. Existing ones are lowercased; when two only differed by case
     * ("Mali" and "mali"), the oldest account (by created_at, then id) keeps the handle and each later one
     * gets the first free numeric suffix that fits in 20 characters ("mali2", "mali3"...), as the migration
     * that first gave every account a handle did.
     */
    public function up(): void
    {
        $users = DB::table('users')
            ->whereNotNull('username')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'username']);

        $taken = $users->mapWithKeys(fn (object $user) => [strtolower($user->username) => true])->all();
        $renames = [];

        foreach ($users->groupBy(fn (object $user) => strtolower($user->username)) as $handle => $owners) {
            foreach ($owners->values() as $position => $user) {
                $target = (string) $handle;

                if ($position > 0) {
                    $target = $this->firstFreeSuffix((string) $handle, $taken);
                    $taken[$target] = true;
                }

                if ($target !== $user->username) {
                    $renames[$user->id] = $target;
                }
            }
        }

        if ($renames === []) {
            return;
        }

        DB::transaction(function () use ($renames): void {
            // Parked on a value no handle can have first, so the unique index never sees two rows holding
            // the same handle halfway through (e.g. "Mali" -> "mali" while "mali" -> "mali2").
            foreach (array_keys($renames) as $id) {
                DB::table('users')->where('id', $id)->update(['username' => '~'.$id]);
            }
            foreach ($renames as $id => $username) {
                DB::table('users')->where('id', $id)->update(['username' => $username]);
            }
        });
    }

    /**
     * Reverse the migrations. The original casing is not kept, so there is nothing to restore.
     */
    public function down(): void
    {
        //
    }

    /**
     * @param  array<string, true>  $taken
     */
    private function firstFreeSuffix(string $handle, array $taken): string
    {
        for ($n = 2; ; $n++) {
            $candidate = substr($handle, 0, self::USERNAME_MAX - strlen((string) $n)).$n;

            if (! isset($taken[$candidate])) {
                return $candidate;
            }
        }
    }
};

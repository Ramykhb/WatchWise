<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /** Pictures uploaded here were stored as absolute URLs (baking in APP_URL); keep only "avatars/<file>". */
    private const UPLOADED_URL = '#^https?://[^?\#]+/storage/(avatars/[^/?\#]+)$#';

    /**
     * Only URLs ending in ".../storage/avatars/<file>" are touched, so avatars copied from Google,
     * GitHub or Discord stay as they are.
     */
    public function up(): void
    {
        DB::table('users')
            ->where('avatar', 'like', 'http%/storage/avatars/%')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    if (preg_match(self::UPLOADED_URL, $user->avatar, $match)) {
                        DB::table('users')->where('id', $user->id)->update(['avatar' => $match[1]]);
                    }
                }
            });
    }

    /** Back to the absolute URL the current APP_URL gives. */
    public function down(): void
    {
        DB::table('users')
            ->where('avatar', 'like', 'avatars/%')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    DB::table('users')->where('id', $user->id)->update(['avatar' => Storage::disk('public')->url($user->avatar)]);
                }
            });
    }
};

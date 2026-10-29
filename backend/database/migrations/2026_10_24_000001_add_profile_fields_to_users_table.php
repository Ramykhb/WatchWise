<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->nullable()->unique()->after('name');
            $table->string('location', 120)->nullable()->after('bio');
        });

        // 'followers' joins public/private, so the column becomes a plain string validated in the app.
        if (DB::getDriverName() === 'pgsql') {
            // Laravel creates PostgreSQL enums as varchar columns with a CHECK constraint. Changing
            // the column type alone leaves that old two-value constraint in place.
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_profile_visibility_check');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_visibility', 20)->default('public')->change();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_profile_visibility_allowed_check CHECK (profile_visibility IN ('public', 'private', 'followers'))");
        }

        // Existing accounts get a handle from their name, made unique with a number.
        $taken = [];
        foreach (DB::table('users')->orderBy('id')->get(['id', 'name', 'email']) as $user) {
            $base = Str::of($user->name ?: Str::before($user->email, '@'))->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '')->limit(20, '')->toString() ?: 'member';
            $username = $base;
            for ($n = 2; in_array($username, $taken, true); $n++) {
                $username = substr($base, 0, 20 - strlen((string) $n)).$n;
            }
            $taken[] = $username;
            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_profile_visibility_allowed_check');
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_profile_visibility_check CHECK (profile_visibility IN ('public', 'private'))");
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'location']);
        });
    }
};

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
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_visibility', 20)->default('public')->change();
        });

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
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'location']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Accounts made by Google, GitHub or Discord sign-in store no password: `password IS NULL` is the one
     * signal that a member has none (password sign-in refuses them; Settings offers "set" instead of "change").
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations. Accounts without a password get a random one nobody knows (they sign in
     * through their social login or "Forgot password"), since the column cannot hold NULL again.
     */
    public function down(): void
    {
        DB::table('users')->whereNull('password')->update(['password' => Hash::make(Str::random(40))]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};

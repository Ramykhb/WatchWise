<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A counter raised whenever every session of the account is revoked (password set, change or reset,
     * email change, social takeover of an unverified account, admin actions). Access tokens carry the
     * version they were issued with (`sv` claim) and only an exact match is accepted, so revoking sessions
     * also ends access tokens that have not expired yet.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('session_version')->default(0)->after('password_set_without_proof_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('session_version');
        });
    }
};

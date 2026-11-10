<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Set whenever every session of the account is revoked (password set, change or reset, email change,
     * social takeover of an unverified account, admin actions). Access tokens issued before it (by whole
     * seconds) are refused, so revoking sessions also ends access tokens that have not expired yet.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('sessions_invalidated_at')->nullable()->after('password_set_without_proof_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sessions_invalidated_at');
        });
    }
};

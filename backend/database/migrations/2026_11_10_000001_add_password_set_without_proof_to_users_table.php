<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Set when an account made by social sign-in chooses its first password in Settings, where no
     * current password can be asked for. Until the owner proves the inbox (a password reset through the
     * emailed link, or a completed email change), that password does not count as proof for an email
     * change, so the current address still has to approve it.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_set_without_proof_at')->nullable()->after('password');
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
};

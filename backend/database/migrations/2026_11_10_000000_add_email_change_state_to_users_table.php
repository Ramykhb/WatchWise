<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `pending_email_nonce` is a random value set on every email-change request and mixed into the
     * links' HMAC, so a new request (even back to an earlier address) voids every older link.
     * `pending_email_authorized_at` is set once the current address agreed to the change: straight
     * away when the member typed their password, or when an account without a password of its own
     * opens the link mailed to its current address. The new address only gets its link after that.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pending_email_nonce', 64)->nullable()->after('pending_email');
            $table->timestamp('pending_email_authorized_at')->nullable()->after('pending_email_nonce');
        });

        // Links mailed before this change used the old hash and can no longer be confirmed.
        DB::table('users')->whereNotNull('pending_email')->update(['pending_email' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pending_email_nonce', 'pending_email_authorized_at']);
        });
    }
};

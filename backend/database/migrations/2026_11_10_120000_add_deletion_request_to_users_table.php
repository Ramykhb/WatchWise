<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A pending account deletion confirmed from the inbox (accounts whose password proves nothing).
     * `deletion_nonce` is random per request and mixed into the links' HMAC, so a newer request or a
     * cancellation voids every older link; `deletion_requested_at` tells Settings it is still pending.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deletion_requested_at')->nullable()->after('password_set_without_proof_at');
            $table->string('deletion_nonce', 64)->nullable()->after('deletion_requested_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['deletion_requested_at', 'deletion_nonce']);
        });
    }
};

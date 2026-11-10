<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the link a pending email change is waiting for was last mailed (request, approval or resend). The
     * links expire on their own after `EmailChangeService::EXPIRES_MINUTES`, but the database had no time to
     * tell a stale pending change from a live one; `users:prune-pending` reads this column.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('pending_email_link_sent_at')->nullable()->after('pending_email_authorized_at');
        });

        // Changes pending while this ships get a fresh window instead of being pruned at once.
        DB::table('users')->whereNotNull('pending_email')->update(['pending_email_link_sent_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pending_email_link_sent_at');
        });
    }
};

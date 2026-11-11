<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `auth:prune-refresh-tokens` deletes rows by `expires_at` and by `revoked_at`; without these indexes every
     * hourly run would scan the whole table.
     */
    public function up(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->index('expires_at');
            $table->index('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropIndex(['revoked_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether the login this token belongs to asked to be remembered ("Remember me"). Copied on every rotation.
     * Existing tokens are remembered, as every login was before the option existed.
     */
    public function up(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->boolean('remember')->default(true)->after('family_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->dropColumn('remember');
        });
    }
};

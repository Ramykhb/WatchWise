<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the login a refresh family belongs to started. Set at login, copied on every rotation, and used to cap
     * the family's absolute lifetime (`jwt.refresh_family_max_days`). Existing rows get the earliest `created_at`
     * of their family (the login), or now when that is missing, so live sessions keep their real age.
     */
    public function up(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->timestamp('family_started_at')->nullable()->after('family_id');
        });

        DB::table('refresh_tokens')
            ->select('family_id', DB::raw('MIN(created_at) as started_at'))
            ->groupBy('family_id')
            ->orderBy('family_id')
            ->chunk(500, function ($families): void {
                foreach ($families as $family) {
                    DB::table('refresh_tokens')
                        ->where('family_id', $family->family_id)
                        ->update(['family_started_at' => $family->started_at ?? now()]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->dropColumn('family_started_at');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Provider access tokens were stored (encrypted) but never read, so they are no longer saved and the ones
     * already stored are erased. `social_accounts.access_token` was created nullable and is kept for now, so no
     * schema change is needed; dropping it is a separate change.
     */
    public function up(): void
    {
        DB::table('social_accounts')->whereNotNull('access_token')->update(['access_token' => null]);
    }

    /** The erased tokens cannot be brought back. */
    public function down(): void {}
};

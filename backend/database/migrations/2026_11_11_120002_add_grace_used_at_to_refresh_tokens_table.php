<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a rotated token used its one reuse grace (a second refresh with the same token inside
     * `jwt.refresh_reuse_grace_seconds`). Null means unused; any later reuse of the token revokes its family.
     */
    public function up(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->timestamp('grace_used_at')->nullable()->after('replaced_by');
        });
    }

    public function down(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->dropColumn('grace_used_at');
        });
    }
};

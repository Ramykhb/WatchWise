<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('pending_follow_requester_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->unique(['user_id', 'pending_follow_requester_id'], 'notifications_pending_follow_unique');
        });

        // Older requests used only JSON. Keep one per pair before assigning the unique key.
        DB::table('notifications')->where('type', 'follow_request')->orderBy('id')->chunkById(500, function ($requests) {
            foreach ($requests as $request) {
                $requesterId = (int) (json_decode($request->data, true)['actor_id'] ?? 0);
                if (! DB::table('users')->where('id', $requesterId)->exists()) {
                    DB::table('notifications')->where('id', $request->id)->delete();

                    continue;
                }

                $exists = DB::table('notifications')->where('user_id', $request->user_id)
                    ->where('pending_follow_requester_id', $requesterId)->exists();
                if ($exists) {
                    DB::table('notifications')->where('id', $request->id)->delete();
                } else {
                    DB::table('notifications')->where('id', $request->id)->update(['pending_follow_requester_id' => $requesterId]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique('notifications_pending_follow_unique');
            $table->dropConstrainedForeignId('pending_follow_requester_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A room's members: its creator and everyone who has posted in it. A message is deleted once every member
        // has read it, so membership can no longer be worked out from the messages that are still stored.
        Schema::create('room_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('discussion_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['room_id', 'user_id']);
            $table->index('user_id');
        });

        // Existing rooms keep the members they have today.
        $creators = DB::table('discussion_rooms')->whereNotNull('created_by')->get(['id', 'created_by'])
            ->map(fn (object $room): array => ['room_id' => $room->id, 'user_id' => $room->created_by]);
        $posters = DB::table('messages')->distinct()->get(['room_id', 'user_id'])
            ->map(fn (object $message): array => ['room_id' => $message->room_id, 'user_id' => $message->user_id]);

        $creators->concat($posters)
            ->unique(fn (array $member): string => $member['room_id'].'-'.$member['user_id'])
            ->chunk(500)
            ->each(fn ($chunk) => DB::table('room_members')->insertOrIgnore($chunk->values()->all()));
    }

    public function down(): void
    {
        Schema::dropIfExists('room_members');
    }
};

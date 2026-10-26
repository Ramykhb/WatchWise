<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing rooms stay public: anyone can read them and join.
        Schema::table('discussion_rooms', function (Blueprint $table) {
            $table->boolean('is_private')->default(false)->after('spoilers');
        });

        // People a member invited to a private room. They can read it before answering; joining or declining
        // removes the invitation. Invitees are not members, so messages never wait for them.
        Schema::create('room_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('discussion_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['room_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_invites');
        Schema::table('discussion_rooms', fn (Blueprint $table) => $table->dropColumn('is_private'));
    }
};

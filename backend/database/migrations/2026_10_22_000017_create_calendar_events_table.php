<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('tmdb_id');
            $table->foreignId('movie_night_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('provider', ['google', 'outlook', 'apple']);
            $table->string('external_event_id');
            $table->enum('event_type', ['release', 'movie_night']);
            $table->timestamp('scheduled_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['provider', 'external_event_id']);
            $table->index(['user_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};

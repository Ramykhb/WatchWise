<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movie_nights', function (Blueprint $table): void {
            $table->unsignedInteger('calendar_revision')->default(0);
        });
        Schema::create('movie_night_calendar_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('movie_night_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->boolean('cancelled')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamps();
            $table->unique(['movie_night_id', 'user_id', 'revision'], 'night_calendar_recipient_revision');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movie_night_calendar_deliveries');
        Schema::table('movie_nights', fn (Blueprint $table) => $table->dropColumn('calendar_revision'));
    }
};

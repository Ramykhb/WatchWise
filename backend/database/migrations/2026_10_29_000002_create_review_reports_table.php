<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Members report reviews (one report each per review). A review whose reports are not resolved is
     * "flagged" in the admin queue; `reviews.removed_at` records that moderators removed its text.
     */
    public function up(): void
    {
        Schema::create('review_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('reason', ['Spam', 'Links', 'Spoilers', 'Offensive language']);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['review_id', 'user_id']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->timestamp('removed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('removed_at');
        });
        Schema::dropIfExists('review_reports');
    }
};

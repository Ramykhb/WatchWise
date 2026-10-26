<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A star rating from the Movie page is a review without text, so content and sentiment become
     * optional. Each member has at most one review (and so one rating) per film.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->text('content')->nullable()->change();
            $table->enum('sentiment', ['positive', 'neutral', 'negative'])->nullable()->change();
            $table->unique(['user_id', 'tmdb_id']);
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'tmdb_id']);
        });
    }
};

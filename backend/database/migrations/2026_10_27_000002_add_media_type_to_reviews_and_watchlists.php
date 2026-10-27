<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TMDB numbers movies and TV shows separately, so the same tmdb_id can be a movie and a show.
     * Ratings (reviews) and watchlist rows now say which one they are; existing rows are movies.
     * The "one per member" unique index includes the type. The new index is added before the old
     * one is dropped, so the user_id foreign key always has an index (MySQL requires one).
     */
    public function up(): void
    {
        foreach (['reviews', 'watchlists'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('media_type', 5)->default('movie')->after('tmdb_id');
            });
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->unique(['user_id', 'tmdb_id', 'media_type'], "{$table}_user_film_unique");
                $blueprint->dropUnique(['user_id', 'tmdb_id']);
            });
        }
    }

    public function down(): void
    {
        foreach (['reviews', 'watchlists'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->unique(['user_id', 'tmdb_id']);
                $blueprint->dropUnique("{$table}_user_film_unique");
                $blueprint->dropColumn('media_type');
            });
        }
    }
};

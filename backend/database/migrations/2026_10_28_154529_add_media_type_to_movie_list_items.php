<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lists can hold TV shows as well as movies. TMDB numbers them separately, so each item now says
     * which one it is (existing items are movies) and "once per list" is per title and type. The new
     * index is added before the old one is dropped, so the movie_list_id foreign key always has an
     * index (MySQL requires one).
     */
    public function up(): void
    {
        Schema::table('movie_list_items', function (Blueprint $table) {
            $table->string('media_type', 5)->default('movie')->after('tmdb_id');
        });
        Schema::table('movie_list_items', function (Blueprint $table) {
            $table->unique(['movie_list_id', 'tmdb_id', 'media_type'], 'movie_list_items_list_film_unique');
            $table->dropUnique(['movie_list_id', 'tmdb_id']);
        });
    }

    /**
     * The old schema can't hold TV shows: their list items are deleted first. Movie items stay, and
     * with at most one movie item per list and film the old unique index can be restored.
     */
    public function down(): void
    {
        DB::table('movie_list_items')->where('media_type', '!=', 'movie')->delete();
        Schema::table('movie_list_items', function (Blueprint $table) {
            $table->unique(['movie_list_id', 'tmdb_id']);
            $table->dropUnique('movie_list_items_list_film_unique');
            $table->dropColumn('media_type');
        });
    }
};

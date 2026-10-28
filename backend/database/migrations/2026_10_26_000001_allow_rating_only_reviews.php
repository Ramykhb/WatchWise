<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A star rating from the Movie page is a review without text, so content and sentiment become
     * optional. Each member has at most one review (and so one rating) per film.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Laravel's PostgreSQL enum is a varchar plus a CHECK constraint. enum()->change()
            // incorrectly puts the CHECK expression into ALTER COLUMN TYPE, which PostgreSQL rejects.
            DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS reviews_sentiment_check');

            Schema::table('reviews', function (Blueprint $table) {
                $table->text('content')->nullable()->change();
                $table->unique(['user_id', 'tmdb_id']);
            });

            DB::statement('ALTER TABLE reviews ALTER COLUMN sentiment DROP NOT NULL');
            DB::statement("ALTER TABLE reviews ADD CONSTRAINT reviews_sentiment_allowed_check CHECK (sentiment IN ('positive', 'neutral', 'negative'))");

            return;
        }

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

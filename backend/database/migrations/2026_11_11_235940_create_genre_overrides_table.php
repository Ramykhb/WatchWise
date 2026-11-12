<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Local overrides layered on top of TMDB's genres, which stay the source of truth: a display name, a hidden
     * flag and a position in the list members see. `tmdb_id` has no foreign key (TMDB owns it), and a movie genre
     * and a tv genre can share an id, so a row is identified by `type` and `tmdb_id` together.
     */
    public function up(): void
    {
        Schema::create('genre_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('type', 5);
            $table->unsignedInteger('tmdb_id');
            $table->string('name', 60)->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->unsignedSmallInteger('position')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['type', 'tmdb_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('genre_overrides');
    }
};

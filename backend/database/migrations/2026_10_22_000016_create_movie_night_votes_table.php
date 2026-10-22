<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movie_night_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movie_night_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('tmdb_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['movie_night_id', 'user_id']);
            $table->index(['movie_night_id', 'tmdb_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movie_night_votes');
    }
};

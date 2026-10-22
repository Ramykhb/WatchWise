<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_feed', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['review', 'rating', 'watchlist_add', 'list_created', 'list_movie_added']);
            $table->unsignedBigInteger('tmdb_id')->nullable();
            $table->unsignedBigInteger('reference_id');
            $table->string('reference_type');
            $table->boolean('is_public')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_feed');
    }
};

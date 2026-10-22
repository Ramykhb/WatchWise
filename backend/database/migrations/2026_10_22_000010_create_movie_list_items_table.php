<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movie_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movie_list_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('tmdb_id');
            $table->timestamp('added_at')->useCurrent();
            $table->unique(['movie_list_id', 'tmdb_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movie_list_items');
    }
};

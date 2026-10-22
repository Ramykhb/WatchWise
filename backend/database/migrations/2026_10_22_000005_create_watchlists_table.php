<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watchlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('tmdb_id');
            $table->enum('status', ['to_watch', 'watching', 'watched'])->default('to_watch');
            $table->timestamp('watched_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'tmdb_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchlists');
    }
};

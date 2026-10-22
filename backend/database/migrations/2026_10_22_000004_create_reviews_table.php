<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('tmdb_id');
            $table->text('content');
            $table->unsignedTinyInteger('rating');
            $table->enum('sentiment', ['positive', 'neutral', 'negative']);
            $table->boolean('is_approved')->default(false);
            $table->timestamps();
            $table->index(['tmdb_id', 'is_approved']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};

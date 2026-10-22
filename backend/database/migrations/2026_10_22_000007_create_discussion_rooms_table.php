<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussion_rooms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tmdb_id');
            $table->string('name');
            $table->timestamp('created_at')->useCurrent();
            $table->index('tmdb_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussion_rooms');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movie_nights', function (Blueprint $table): void {
            $table->text('share_token')->nullable();
            $table->timestamp('share_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('movie_nights', function (Blueprint $table): void {
            $table->dropColumn(['share_token', 'share_expires_at']);
        });
    }
};

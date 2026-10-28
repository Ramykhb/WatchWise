<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admins can override a catalogue film's director, runtime, release date and genres;
     * `edited_at` marks the films with such overrides.
     */
    public function up(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->string('director')->nullable()->after('runtime');
            $table->timestamp('edited_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->dropColumn(['director', 'edited_at']);
        });
    }
};

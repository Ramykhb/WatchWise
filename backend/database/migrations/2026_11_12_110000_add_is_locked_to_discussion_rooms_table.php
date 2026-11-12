<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A locked room stays readable and joinable, but nobody can post in it until staff unlock it.
     */
    public function up(): void
    {
        Schema::table('discussion_rooms', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->after('is_private');
        });
    }

    public function down(): void
    {
        Schema::table('discussion_rooms', function (Blueprint $table) {
            $table->dropColumn('is_locked');
        });
    }
};

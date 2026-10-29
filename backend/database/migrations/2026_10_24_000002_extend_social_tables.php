<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Privacy toggles and integrations from Settings, e.g. {"privacy": {"reviews": true}, "integrations": {...}}.
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->json('settings')->nullable()->after('favorite_director_ids');
        });

        // Lists keep the owner's order and an optional note per film.
        Schema::table('movie_list_items', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('tmdb_id');
            $table->string('note', 280)->nullable()->after('position');
        });

        Schema::table('discussion_rooms', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('name')->constrained('users')->nullOnDelete();
            $table->boolean('spoilers')->default(false)->after('created_by');
        });

        Schema::table('movie_nights', function (Blueprint $table) {
            $table->text('note')->nullable()->after('meeting_link');
        });

        // Guests can also answer "maybe".
        if (DB::getDriverName() === 'pgsql') {
            // Laravel represents PostgreSQL enums with CHECK constraints; drop the old allowed
            // values before widening the status column.
            DB::statement('ALTER TABLE movie_night_invites DROP CONSTRAINT IF EXISTS movie_night_invites_status_check');
        }

        Schema::table('movie_night_invites', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->change();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE movie_night_invites ADD CONSTRAINT movie_night_invites_status_allowed_check CHECK (status IN ('pending', 'accepted', 'maybe', 'declined'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE movie_night_invites DROP CONSTRAINT IF EXISTS movie_night_invites_status_allowed_check');
            DB::statement("ALTER TABLE movie_night_invites ADD CONSTRAINT movie_night_invites_status_check CHECK (status IN ('pending', 'accepted', 'declined'))");
        }

        Schema::table('movie_nights', fn (Blueprint $table) => $table->dropColumn('note'));
        Schema::table('discussion_rooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('spoilers');
        });
        Schema::table('movie_list_items', fn (Blueprint $table) => $table->dropColumn(['position', 'note']));
        Schema::table('user_preferences', fn (Blueprint $table) => $table->dropColumn('settings'));
    }
};

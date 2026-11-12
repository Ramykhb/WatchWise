<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One table for every report a member files: reviews, room messages, movie lists, discussion rooms and
     * member profiles. The target is polymorphic (`target_type` + `target_id`) with no foreign key, so a report
     * outlives the content it is about and the staff queue shows it as removed. `target_label` and
     * `target_excerpt` snapshot what the target looked like when it was reported (a message is gone within a
     * day), so staff can still read what was reported. Every row of `review_reports`
     * moves over with its id, reporter, reason, timestamps and open/resolved state, and that table is dropped.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id');
            $table->string('reason', 50);
            $table->string('details', 500)->nullable();
            $table->string('target_label', 120)->nullable();
            $table->string('target_excerpt', 200)->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolution_note', 500)->nullable();
            $table->timestamps();
            $table->unique(['reporter_id', 'target_type', 'target_id']);
            $table->index(['target_type', 'target_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        DB::table('reports')->insertUsing(
            ['id', 'reporter_id', 'target_type', 'target_id', 'reason', 'status', 'resolved_at', 'created_at', 'updated_at'],
            DB::table('review_reports')->select([
                'id',
                'user_id',
                DB::raw("'review'"),
                'review_id',
                'reason',
                DB::raw("CASE WHEN resolved_at IS NULL THEN 'open' ELSE 'resolved' END"),
                'resolved_at',
                'created_at',
                'updated_at',
            ]),
        );

        $this->advanceSequence('reports');

        Schema::drop('review_reports');
    }

    /**
     * Review reports go back to `review_reports`; reports about anything else have no home there and are dropped.
     */
    public function down(): void
    {
        Schema::create('review_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('reason', ['Spam', 'Links', 'Spoilers', 'Offensive language']);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['review_id', 'user_id']);
        });

        DB::table('review_reports')->insertUsing(
            ['id', 'user_id', 'review_id', 'reason', 'resolved_at', 'created_at', 'updated_at'],
            DB::table('reports')
                ->where('target_type', 'review')
                ->whereIn('target_id', DB::table('reviews')->select('id'))
                ->select(['id', 'reporter_id', 'target_id', 'reason', 'resolved_at', 'created_at', 'updated_at']),
        );

        $this->advanceSequence('review_reports');

        Schema::drop('reports');
    }

    /** The rows above were inserted with their ids, so PostgreSQL's id sequence has to move past them. */
    private function advanceSequence(string $table): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 1), (SELECT COUNT(*) > 0 FROM {$table}))");
        }
    }
};

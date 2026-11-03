<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->boolean('read_tracking_started')->default(false);
        });

        DB::table('messages')->whereExists(fn (Builder $receipts) => $receipts
            ->selectRaw('1')->from('notifications')->where('type', 'room_message')
            ->whereColumn('notifications.data->message_id', 'messages.id'))
            ->update(['read_tracking_started' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('read_tracking_started');
        });
    }
};

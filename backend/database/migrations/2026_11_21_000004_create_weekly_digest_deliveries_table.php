<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_digest_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('week');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_digest_deliveries');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Why and until when staff suspended an account. Both are set only while `is_active` is false and cleared
     * when the account is reinstated, by staff or by `admin:lift-suspensions` once `suspended_until` has passed.
     * The reason is shown to the member in the suspension email; neither column is ever part of a member-facing response.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('suspension_reason', 255)->nullable()->after('is_active');
            $table->timestamp('suspended_until')->nullable()->after('suspension_reason')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['suspended_until']);
            $table->dropColumn(['suspension_reason', 'suspended_until']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dateTime('correction_dm_sent_at')->nullable()->after('closed_by');
            $table->dateTime('long_reminder_sent_at')->nullable()->after('correction_dm_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropColumn(['correction_dm_sent_at', 'long_reminder_sent_at']);
        });
    }
};

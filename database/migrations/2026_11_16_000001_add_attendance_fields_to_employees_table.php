<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Discord IDs are 64-bit integers. They are stored as strings: a
            // number above 2^53 would silently change in JavaScript.
            $table->string('discord_user_id', 32)->nullable()->unique()->after('title');
            $table->string('contract_type', 30)->default('fixed_monthly')->after('discord_user_id');
            // Hourly rate in centimes, used by phase 4. Never a float.
            $table->unsignedBigInteger('hourly_rate')->nullable()->after('contract_type');
            $table->unsignedSmallInteger('expected_minutes_per_day')->default(480)->after('hourly_rate');
            $table->json('work_days')->nullable()->after('expected_minutes_per_day');
            $table->time('schedule_start')->nullable()->after('work_days');
            $table->time('schedule_end')->nullable()->after('schedule_start');
            $table->unsignedSmallInteger('late_grace_minutes')->default(15)->after('schedule_end');
            $table->boolean('reminders_enabled')->default(true)->after('late_grace_minutes');

            $table->index(['status', 'discord_user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['status', 'discord_user_id']);
            $table->dropUnique(['discord_user_id']);
            $table->dropColumn([
                'discord_user_id',
                'contract_type',
                'hourly_rate',
                'expected_minutes_per_day',
                'work_days',
                'schedule_start',
                'schedule_end',
                'late_grace_minutes',
                'reminders_enabled',
            ]);
        });
    }
};

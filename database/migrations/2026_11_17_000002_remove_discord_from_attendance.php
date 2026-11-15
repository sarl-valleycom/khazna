<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance moved from a Discord bot to the employee web page, so everything
 * only the bot used goes: the Discord link, the DM reminders and their
 * bookkeeping, the Discord channel IDs and the digests' settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropIndex(['status', 'discord_user_id']);
            $table->dropUnique(['discord_user_id']);
            $table->dropColumn(['discord_user_id', 'reminders_enabled']);
        });

        Schema::table('offices', function (Blueprint $table): void {
            $table->dropColumn('attendance_channel_id');
        });

        Schema::table('attendance_sessions', function (Blueprint $table): void {
            $table->dropColumn(['correction_dm_sent_at', 'long_reminder_sent_at']);
            $table->string('source', 20)->default('web')->change();
        });

        Schema::table('attendance_breaks', function (Blueprint $table): void {
            $table->dropColumn('away_reminder_sent_at');
        });

        Schema::table('attendance_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'digest_time',
                'reminders_enabled',
                'reminder_after_minutes',
                'away_limit_minutes',
                'over_10h_reminder_enabled',
                'owner_digest_channel_id',
                'reports_channel_id',
            ]);
        });

        Schema::dropIfExists('attendance_reminders');
    }

    public function down(): void
    {
        Schema::create('attendance_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->string('type', 30);
            $table->timestamps();

            $table->unique(['employee_id', 'date', 'type']);
        });

        Schema::table('attendance_settings', function (Blueprint $table): void {
            $table->time('digest_time')->default('18:00:00');
            $table->boolean('reminders_enabled')->default(true);
            $table->unsignedSmallInteger('reminder_after_minutes')->default(30);
            $table->unsignedSmallInteger('away_limit_minutes')->default(90);
            $table->boolean('over_10h_reminder_enabled')->default(true);
            $table->string('owner_digest_channel_id', 32)->nullable();
            $table->string('reports_channel_id', 32)->nullable();
        });

        Schema::table('attendance_breaks', function (Blueprint $table): void {
            $table->dateTime('away_reminder_sent_at')->nullable()->after('paid');
        });

        Schema::table('attendance_sessions', function (Blueprint $table): void {
            $table->dateTime('correction_dm_sent_at')->nullable()->after('closed_by');
            $table->dateTime('long_reminder_sent_at')->nullable()->after('correction_dm_sent_at');
            $table->string('source', 20)->default('discord')->change();
        });

        Schema::table('offices', function (Blueprint $table): void {
            $table->string('attendance_channel_id', 32)->nullable()->after('city');
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->string('discord_user_id', 32)->nullable()->unique()->after('title');
            $table->boolean('reminders_enabled')->default(true)->after('late_grace_minutes');
            $table->index(['status', 'discord_user_id']);
        });
    }
};

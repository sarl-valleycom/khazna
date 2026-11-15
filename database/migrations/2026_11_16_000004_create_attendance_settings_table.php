<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->time('auto_close_time')->default('23:59:00');
            $table->time('digest_time')->default('18:00:00');
            $table->boolean('reminders_enabled')->default(true);
            $table->unsignedSmallInteger('reminder_after_minutes')->default(30);
            $table->unsignedSmallInteger('away_limit_minutes')->default(90);
            $table->unsignedSmallInteger('paid_break_minutes')->default(0);
            $table->boolean('self_correction_enabled')->default(false);
            $table->unsignedSmallInteger('self_correction_hours')->default(24);
            $table->boolean('over_10h_reminder_enabled')->default(true);
            $table->string('owner_digest_channel_id', 32)->nullable();
            $table->string('reports_channel_id', 32)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_settings');
    }
};

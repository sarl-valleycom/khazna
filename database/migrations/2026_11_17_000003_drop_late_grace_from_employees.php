<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The late grace only fed the late reminders and digests, which went with the
 * Discord bot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn('late_grace_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->unsignedSmallInteger('late_grace_minutes')->default(15)->after('schedule_end');
        });
    }
};

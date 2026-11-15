<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            // Keyed hash of the personal attendance code, never the code itself.
            $table->string('attendance_code_hash', 64)->nullable()->unique()->after('discord_user_id');
            $table->timestamp('attendance_code_set_at')->nullable()->after('attendance_code_hash');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropUnique(['attendance_code_hash']);
            $table->dropColumn(['attendance_code_hash', 'attendance_code_set_at']);
        });
    }
};

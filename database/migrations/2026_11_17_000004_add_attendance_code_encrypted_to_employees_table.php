<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            // The code, encrypted with the app key, so the owner can look it up again.
            $table->text('attendance_code_encrypted')->nullable()->after('attendance_code_hash');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn('attendance_code_encrypted');
        });
    }
};

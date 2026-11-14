<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Payments reversed before the is_reversal marker existed wrote an opposite entry
        // with this description. Mark them, so reports net them instead of counting both.
        DB::table('account_entries')
            ->where('source_type', 'App\\Models\\Payment')
            ->where('description', 'like', 'Reversal of payment #%')
            ->update(['is_reversal' => true]);

        Schema::table('employees', function (Blueprint $table) {
            // The day someone stopped being on the team, so a month they worked part of can still be closed.
            $table->date('left_on')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('left_on');
        });
    }
};

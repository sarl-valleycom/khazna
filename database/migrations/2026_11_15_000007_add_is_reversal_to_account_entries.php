<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_entries', function (Blueprint $table) {
            // The opposite entry that undoes an earlier one. Reports net it against the
            // direction it cancels, so a reversed payment is not "money in" and "money out".
            $table->boolean('is_reversal')->default(false)->after('is_transfer');
        });
    }

    public function down(): void
    {
        Schema::table('account_entries', function (Blueprint $table) {
            $table->dropColumn('is_reversal');
        });
    }
};

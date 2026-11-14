<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_entries', function (Blueprint $table) {
            // The two legs of a transfer move money between accounts: they are not income or
            // expense, so reports leave them out. A transfer fee is a normal expense, not a leg.
            $table->boolean('is_transfer')->default(false)->after('is_payroll');
        });
    }

    public function down(): void
    {
        Schema::table('account_entries', function (Blueprint $table) {
            $table->dropColumn('is_transfer');
        });
    }
};

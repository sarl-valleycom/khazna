<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dues', function (Blueprint $table) {
            // Set when pausing, canceling or rescheduling a contract cancels a due, so
            // resuming the contract can bring back exactly those dues and no others.
            $table->boolean('canceled_by_contract')->default(false)->after('canceled_reason');
        });
    }

    public function down(): void
    {
        Schema::table('dues', function (Blueprint $table) {
            $table->dropColumn('canceled_by_contract');
        });
    }
};

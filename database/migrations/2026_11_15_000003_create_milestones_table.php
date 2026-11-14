<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->string('label');
            $table->unsignedBigInteger('amount_cents');
            $table->date('due_on')->nullable();
            $table->string('trigger', 20)->default('date');
            $table->date('delivered_at')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamps();

            $table->index(['contract_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milestones');
    }
};

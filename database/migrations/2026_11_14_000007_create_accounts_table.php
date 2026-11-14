<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained('offices')->restrictOnDelete();
            $table->string('name');
            $table->string('type', 20);
            $table->string('currency', 3)->default('DZD');
            // Signed: an overdraft or a negative opening position is legal.
            $table->bigInteger('opening_balance_cents')->default(0);
            $table->date('opening_date');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['office_id', 'name']);
            $table->index(['office_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};

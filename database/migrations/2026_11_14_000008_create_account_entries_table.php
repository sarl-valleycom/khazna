<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('direction', 10);
            $table->unsignedBigInteger('amount_cents');
            $table->date('entry_date');
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            // Categories arrive in phase 2; the column exists so entries never need a rewrite.
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['account_id', 'entry_date', 'id']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_entries');
    }
};

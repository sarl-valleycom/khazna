<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('due_postponements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('due_id')->constrained('dues')->cascadeOnDelete();
            $table->date('old_due_on');
            $table->date('new_due_on');
            $table->text('reason');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('due_postponements');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('due_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('due_id')->constrained('dues')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 20)->default('whatsapp');
            $table->text('template');
            $table->text('message');
            $table->string('phone', 20);
            $table->timestamp('created_at')->nullable();

            $table->index(['due_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('due_reminders');
    }
};

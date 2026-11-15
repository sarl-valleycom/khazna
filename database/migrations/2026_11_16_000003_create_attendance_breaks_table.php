<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('attendance_sessions')->restrictOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->string('reason', 500)->nullable();
            $table->boolean('paid')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unsignedBigInteger('open_session_id')->nullable()->storedAs(
                'if(`ended_at` is null and `deleted_at` is null, `session_id`, null)',
            );

            $table->unique('open_session_id');
            $table->index(['session_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_breaks');
    }
};

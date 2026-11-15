<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->string('status', 20)->default('open');
            $table->string('review_status', 20)->default('ok');
            $table->text('report')->nullable();
            $table->text('blockers')->nullable();
            $table->string('source', 20)->default('discord');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unsignedBigInteger('open_employee_id')->nullable()->storedAs(
                'if(`ended_at` is null and `deleted_at` is null, `employee_id`, null)',
            );

            $table->unique('open_employee_id');
            $table->index(['employee_id', 'started_at']);
            $table->index(['status', 'review_status']);
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};

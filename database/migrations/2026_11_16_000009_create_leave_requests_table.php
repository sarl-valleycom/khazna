<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('type', 20)->default('paid');
            $table->date('from');
            $table->date('to');
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status']);
            $table->index(['status', 'from', 'to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};

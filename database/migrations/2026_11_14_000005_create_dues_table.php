<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dues', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);
            $table->string('direction', 10);
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('office_id')->constrained('offices')->restrictOnDelete();
            $table->date('period');
            $table->date('due_on');
            $table->unsignedBigInteger('amount_due_cents');
            $table->unsignedBigInteger('amount_paid_cents')->default(0);
            $table->string('status', 20)->default('pending');
            $table->string('description')->nullable();
            $table->text('canceled_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['contract_id', 'period']);
            $table->index(['status', 'due_on']);
            $table->index(['direction', 'due_on']);
            $table->index(['client_id', 'due_on']);
            $table->index(['office_id', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dues');
    }
};

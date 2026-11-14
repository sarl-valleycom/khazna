<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->string('direction', 10);
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('office_id')->constrained('offices')->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->string('cycle', 20);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status', 20)->default('active');
            $table->boolean('auto_renew')->default(false);
            $table->text('notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['office_id', 'status']);
            $table->index(['client_id', 'status']);
            $table->index('starts_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};

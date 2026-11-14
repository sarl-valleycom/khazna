<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // A contract has exactly one party: a client (money in) or a supplier (money out).
            $table->foreignId('client_id')->nullable()->change();
            $table->foreignId('supplier_id')->nullable()->after('client_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->after('supplier_id')->constrained('categories')->restrictOnDelete();
        });

        Schema::table('dues', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->change();
            $table->foreignId('supplier_id')->nullable()->after('client_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->after('supplier_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('milestone_id')->nullable()->after('employee_id')->constrained('milestones')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->after('milestone_id')->constrained('categories')->restrictOnDelete();

            // A milestone waiting for delivery has no date yet, and only a milestone may.
            $table->date('due_on')->nullable()->change();
            // Milestone dues have no billing period; the unique keys only guard periodic dues.
            $table->date('period')->nullable()->change();

            $table->unique(['employee_id', 'period']);
            $table->unique('milestone_id');
            $table->index(['direction', 'status', 'due_on']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('paid_on');
        });

        Schema::table('account_entries', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            // Salary payments and advances: hidden from anyone without the salary permission.
            $table->boolean('is_payroll')->default(false)->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('account_entries', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('is_payroll');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['paid_on']);
        });

        Schema::table('dues', function (Blueprint $table) {
            $table->dropIndex(['direction', 'status', 'due_on']);
            $table->dropUnique(['milestone_id']);
            $table->dropUnique(['employee_id', 'period']);
            $table->dropConstrainedForeignId('category_id');
            $table->dropConstrainedForeignId('milestone_id');
            $table->dropConstrainedForeignId('employee_id');
            $table->dropConstrainedForeignId('supplier_id');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};

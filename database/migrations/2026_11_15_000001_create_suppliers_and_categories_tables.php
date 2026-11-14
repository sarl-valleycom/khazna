<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kind', 10);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['name', 'kind']);
        });

        // The categories every installation needs. Owner and finance add more from the screen.
        $now = now();
        $costs = ['Hosting', 'Domains', 'Tools', 'Rent', 'Salaries', 'Taxes', 'Bank fees', 'Other', 'Reconciliation difference'];
        $rows = array_map(fn (string $name): array => [
            'name' => $name,
            'kind' => 'cost',
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $costs);
        $rows[] = ['name' => 'Other income', 'kind' => 'income', 'active' => true, 'created_at' => $now, 'updated_at' => $now];

        DB::table('categories')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
        Schema::dropIfExists('suppliers');
    }
};

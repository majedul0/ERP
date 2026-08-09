<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('unit', 16);

            // Whole units, like every other quantity and amount in this
            // application. A material weighed in fractions is recorded in its
            // smaller unit; see App\Enums\MaterialUnit.
            $table->integer('stock_quantity')->default(0);

            // The level at or below which the Stock Levels screen calls this
            // material low. 0 means "never warn".
            $table->integer('reorder_level')->default(0);

            // What one unit last cost to buy. Whole currency units, no
            // fractions: see App\Support\Money.
            $table->bigInteger('unit_cost')->default(0);

            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Codes identify a material within a company, not across the
            // platform — two companies may both use "SUG-01".
            $table->unique(['team_id', 'code']);
            $table->index(['team_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_materials');
    }
};

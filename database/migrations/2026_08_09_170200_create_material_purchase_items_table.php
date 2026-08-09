<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raw_material_id')->constrained();

            /*
             * Copied at the moment of purchase, exactly like invoice items copy
             * a product's name and price. Renaming a material later must not
             * rewrite what a delivery note from last year said it was.
             */
            $table->string('material_name');
            $table->string('material_code');
            $table->string('unit', 16);

            $table->unsignedInteger('line_number');
            $table->integer('quantity');

            // Whole currency units, and line_total is quantity × unit_cost as
            // the server computed it — never as the browser reported it.
            $table->bigInteger('unit_cost')->default(0);
            $table->bigInteger('line_total')->default(0);

            $table->timestamps();

            $table->index('raw_material_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_purchase_items');
    }
};

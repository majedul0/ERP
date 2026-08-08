<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku');
            $table->unsignedInteger('carton_size')->default(1);

            // Prices in minor units (e.g. poisha), never floats.
            $table->bigInteger('distributor_price')->default(0);
            $table->bigInteger('trade_price')->default(0);
            $table->bigInteger('mrp')->default(0);

            $table->integer('stock_quantity')->default(0);
            $table->string('photo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // SKUs identify a product within a company, not across the platform.
            $table->unique(['team_id', 'sku']);
            $table->index(['team_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

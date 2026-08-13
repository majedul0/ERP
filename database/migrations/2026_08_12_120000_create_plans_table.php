<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // Whole currency units, like every other amount in this system.
            // See App\Support\Money.
            $table->bigInteger('price')->default(0);

            $table->string('period', 16);

            /*
             * Retired plans stay on the table rather than being deleted: a
             * company may still be on one, and its payments name it.
             */
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('proprietor_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('thana')->nullable();
            $table->string('district')->nullable();
            $table->string('division')->nullable();

            /*
             * What the distributor currently owes, in minor units, maintained
             * by invoice and payment writes. Integers because money in floats
             * drifts; see App\Support\Money.
             */
            $table->bigInteger('balance')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributors');
    }
};

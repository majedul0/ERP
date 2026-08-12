<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
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
             * What the company owes this vendor — the mirror of a distributor's
             * balance, pointing the other way. Positive means money is payable;
             * negative means the vendor holds an advance.
             *
             * Derived, never typed: written by ReplayVendorBalance from the
             * bills and payments, exactly as a distributor's balance is.
             */
            $table->bigInteger('balance')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};

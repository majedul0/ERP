<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The accounts a company receives money into. A lookup rather than a
        // free-text field so a statement can be filtered by bank later without
        // first cleaning up thirty spellings of the same one.
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('account_number')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'name']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('distributor_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->date('paid_on');

            // Whole currency units, like every other amount in the system.
            $table->bigInteger('amount');

            $table->string('comment')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // The statement reads a distributor's ledger in date order.
            $table->index(['team_id', 'paid_on']);
            $table->index(['distributor_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('banks');
    }
};

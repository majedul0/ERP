<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_id')->nullable()->constrained();
            $table->foreignId('created_by')->constrained('users');

            $table->string('category', 32);
            $table->string('description');

            // A date, not a timestamp: money is spent on a day. `spent_on`
            // decides which period a report counts it in.
            $table->date('spent_on');

            // Whole currency units. No fractions anywhere: see App\Support\Money.
            $table->bigInteger('amount');

            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // The report groups by category within a date range, and the
            // dashboard sums a single day.
            $table->index(['team_id', 'spent_on']);
            $table->index(['team_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};

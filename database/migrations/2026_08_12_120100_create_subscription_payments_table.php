<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained();
            $table->foreignId('recorded_by')->constrained('users');

            $table->bigInteger('amount');

            // bKash, bank transfer, cash — free text because the platform owner
            // decides how they get paid, and a fixed list would be guesswork.
            $table->string('method', 64)->nullable();

            $table->date('paid_on');

            /*
             * The period this payment bought.
             *
             * Stored rather than computed at read time, because it is what
             * `teams.paid_through` is replayed from: the latest `covers_to`
             * across a company's payments *is* the paid-through date. A stored
             * date that is nudged forward drifts the moment a payment is
             * corrected — the lesson already paid for in DistributorLedger.
             */
            $table->date('covers_from');
            $table->date('covers_to');

            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'covers_to']);
            $table->index('paid_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};

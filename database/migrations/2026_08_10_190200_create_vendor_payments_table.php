<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained();
            $table->foreignId('bank_id')->nullable()->constrained();
            $table->foreignId('created_by')->constrained('users');

            // A date, not a timestamp: money is paid on a day.
            $table->date('paid_on');
            $table->bigInteger('amount');
            $table->string('comment')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'paid_on']);
            $table->index('vendor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payments');
    }
};

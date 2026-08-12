<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained();
            $table->foreignId('created_by')->constrained('users');

            /*
             * The vendor's own reference, as printed on the paper they sent.
             *
             * Not a number this system allocates — a bill is somebody else's
             * document, the same reasoning as material purchases. Sales
             * invoices are ours and do get an allocated number.
             */
            $table->string('reference')->nullable();

            $table->string('description')->nullable();
            $table->date('billed_on');

            // Whole currency units. No fractions anywhere: see App\Support\Money.
            $table->bigInteger('amount');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'billed_on']);
            $table->index('vendor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_bills');
    }
};

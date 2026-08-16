<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * One row per company, holding the next return number to hand out.
         *
         * A separate table from `invoice_sequences` rather than a shared one
         * with a type column: returns and invoices are numbered independently
         * (RNV1 and INV1 both exist), and separate rows mean allocating a
         * return number never queues behind an invoice being raised.
         */
        Schema::create('return_sequences', function (Blueprint $table) {
            $table->foreignId('team_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('next_number');
            $table->timestamps();
        });

        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('distributor_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('return_number');
            $table->unsignedBigInteger('sequence_number');

            /*
             * A calendar date, like an invoice's `sold_at` — the ledger orders
             * by document date, and a return and an invoice on the same day are
             * separated by entry time, not by the hour typed on a form.
             */
            $table->date('returned_on');

            $table->text('comment')->nullable();

            /*
             * Whether the goods go back on the shelf.
             *
             * A return and a restock are not the same event: damaged stock comes
             * off the distributor's account but must never become sellable
             * again. The credit is the same either way — this decides only where
             * the goods went.
             */
            $table->boolean('restock')->default(true);

            // Whole currency units. Stored, not derived: a later price change
            // must not rewrite what was credited. See App\Support\Money.
            $table->bigInteger('return_total')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // The hard guarantee behind concurrent numbering: two requests that
            // somehow allocate the same number cannot both commit.
            $table->unique(['team_id', 'return_number']);
            $table->index(['team_id', 'returned_on']);
            $table->index(['team_id', 'distributor_id']);
        });

        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            // Copied, not joined, for the same reason as invoice items: renaming
            // a product later must not rewrite what came back.
            $table->string('product_name');
            $table->string('product_sku')->nullable();

            $table->unsignedInteger('line_number');
            $table->unsignedInteger('quantity');
            $table->bigInteger('unit_price');
            $table->bigInteger('amount');
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->index('sales_return_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
        Schema::dropIfExists('sales_returns');
        Schema::dropIfExists('return_sequences');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * One row per company, holding the next invoice number to hand out.
         * A dedicated table (rather than MAX(invoice_number)+1) means the
         * number can be allocated under a row lock without blocking reads of
         * the invoices table itself.
         */
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->foreignId('team_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('next_number');
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('distributor_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('invoice_number');
            $table->unsignedBigInteger('sequence_number');
            $table->timestamp('sold_at');
            $table->string('delivery_status')->default('pending');

            $table->text('comment')->nullable();
            $table->string('scheme_description')->nullable();

            // All amounts in minor units. Totals are stored, not derived at
            // read time, so a later price change cannot rewrite history.
            $table->bigInteger('scheme_amount')->default(0);
            $table->bigInteger('invoice_total')->default(0);
            $table->bigInteger('discount_total')->default(0);
            $table->bigInteger('previous_dues')->default(0);
            $table->bigInteger('total_amount')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // The hard guarantee behind concurrent numbering: two requests
            // that somehow allocate the same number cannot both commit.
            $table->unique(['team_id', 'invoice_number']);
            $table->index(['team_id', 'sold_at']);
            $table->index(['team_id', 'distributor_id']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            /*
             * Name and SKU are copied, not joined. An invoice is a record of
             * what was sold on the day; renaming or deleting the product later
             * must not silently rewrite it.
             */
            $table->string('product_name');
            $table->string('product_sku')->nullable();

            $table->unsignedInteger('line_number');
            $table->unsignedInteger('carton_quantity')->default(0);
            $table->unsignedInteger('total_quantity');
            $table->bigInteger('unit_price');
            $table->bigInteger('amount');
            $table->bigInteger('discount')->default(0);
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_sequences');
    }
};

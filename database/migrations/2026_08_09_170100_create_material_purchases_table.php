<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');

            $table->string('supplier_name');

            /*
             * The supplier's own bill or challan number, as printed on the
             * paper that came with the goods.
             *
             * Deliberately not a number this system allocates: a purchase is
             * somebody else's document, and inventing a second reference for it
             * would mean two numbers for one delivery and a sequence to keep
             * correct under concurrency for no gain. Sales invoices are ours
             * and do get an allocated number — see App\Support\InvoiceNumbers.
             */
            $table->string('reference')->nullable();

            $table->date('purchased_at');

            // Recomputed by the server from the lines; see RecordMaterialPurchase.
            $table->bigInteger('total_amount')->default(0);

            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'purchased_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_purchases');
    }
};

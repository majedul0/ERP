<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            // Null means "no plan assigned yet" — a company created before it
            // was sold anything, which the panel shows as Not subscribed.
            $table->foreignId('plan_id')->nullable()->after('suspended_at')->constrained();

            /*
             * The last day this company is paid up to.
             *
             * **Derived, never incremented** — recomputed by
             * `App\Actions\Platform\ReplaySubscription` as the latest
             * `covers_to` across their payments. Correcting or deleting a
             * payment therefore fixes this date automatically, and there is no
             * second source of truth to disagree with the payment list.
             */
            $table->date('paid_through')->nullable()->after('plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn('paid_through');
        });
    }
};

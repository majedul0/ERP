<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            /*
             * Print this invoice without the running account on it.
             *
             * Presentation only. `previous_dues` and `total_amount` are still
             * recorded and still replayed, so the distributor's balance and
             * their statement are identical either way — this decides what the
             * paper says, not what is owed.
             *
             * Distinct from `previous_dues_override`, which changes the account
             * itself. Hiding is for handing someone a clean invoice; overriding
             * is for restating what they owed when it began.
             */
            $table->boolean('hide_previous_dues')->default(false)->after('previous_dues_override');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('hide_previous_dues');
        });
    }
};

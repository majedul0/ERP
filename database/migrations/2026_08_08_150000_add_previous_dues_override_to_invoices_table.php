<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An opening balance typed by hand on the invoice screen.
     *
     * `previous_dues` is normally computed by replaying the distributor's
     * account. When someone overrides it — an opening balance for a
     * distributor migrated from paper, or a correction agreed with them — the
     * figure they typed is kept here, and the replay treats this invoice as
     * the point the account restarts from.
     *
     * Null means "computed", which stays the default. Nothing is deleted
     * either way: every invoice and payment remains on the account, and the
     * difference the override introduces is shown on the statement as its own
     * adjustment line so the running balance still adds up.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->bigInteger('previous_dues_override')->nullable()->after('previous_dues');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('previous_dues_override');
        });
    }
};

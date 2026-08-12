<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            /*
             * Whoever runs the platform, as opposed to a company using it.
             *
             * A flag rather than a role on `team_members`, because this person
             * belongs to no company: they create companies, suspend them, and
             * otherwise stay out of the books.
             */
            $table->boolean('is_super_admin')->default(false)->index();
        });

        Schema::table('teams', function (Blueprint $table) {
            /*
             * When set, nobody from this company can sign in or reach any of
             * its screens — for non-payment, typically.
             *
             * A timestamp rather than a boolean so the date is on record, and
             * nullable so lifting a suspension is simply clearing it. Nothing
             * is deleted: their data waits for them.
             */
            $table->timestamp('suspended_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('suspended_at');
        });
    }
};

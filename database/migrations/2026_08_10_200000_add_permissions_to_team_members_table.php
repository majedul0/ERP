<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            /*
             * Permissions chosen for this member specifically.
             *
             * **Null means "follow the role"** — which is not the same as an
             * empty array, and the difference matters: an empty array is a
             * deliberate "this person may do nothing", while null lets the
             * member keep inheriting if the role's defaults are ever widened.
             */
            $table->json('permissions')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }
};

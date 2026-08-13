<?php

use App\Enums\SuspensionMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            /*
             * How a suspension is presented — see App\Enums\SuspensionMode.
             *
             * Only meaningful while `suspended_at` is set; lifting a suspension
             * leaves this alone, so reinstating and re-suspending keeps the
             * choice that was made last time.
             *
             * Defaults to the honest option: anything else has to be chosen.
             */
            $table->string('suspension_mode', 16)
                ->default(SuspensionMode::Notice->value)
                ->after('suspended_at');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('suspension_mode');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            /*
             * The company's own colour, as `#rrggbb`.
             *
             * Null means the house palette — the coffee scale in
             * `resources/css/app.css` — so a company that never opens the
             * setting looks exactly as it did before this column existed, and
             * clearing the setting is a null rather than a "reset" flag.
             *
             * One column rather than three, even though the setting is entered
             * as red, green and blue: they are one colour, and three columns
             * could disagree about whether the colour is set at all. See
             * App\Support\BrandColor, which is the only place that encodes it.
             */
            $table->string('theme_color', 7)->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('theme_color');
        });
    }
};

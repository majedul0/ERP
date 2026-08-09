<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The company's own address and phone, printed in the header of every
     * invoice and challan.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('address')->nullable()->after('logo_path');
            $table->string('phone', 32)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['address', 'phone']);
        });
    }
};

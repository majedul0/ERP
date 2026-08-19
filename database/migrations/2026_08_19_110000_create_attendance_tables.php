<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * How a company's working week is shaped.
         *
         * One row per company, `team_id` as the primary key, so there is
         * exactly one and no code has to decide which of two is current.
         */
        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->foreignId('team_id')->primary()->constrained()->cascadeOnDelete();

            /*
             * ISO-8601 weekday numbers (Mon 1 … Sun 7) that are not worked.
             * Bangladesh keeps Friday and Saturday, which is the default; a
             * company that works six days sets `[5]`, and one that never rests
             * sets `[]`.
             *
             * Stored as the setting, never as a mark on a day: changing it
             * re-derives every past month, exactly as the stock report
             * re-derives when a movement is corrected.
             */
            $table->json('weekend_days')->default('[5,6]');

            // What an hour of overtime is worth, as a company default. Whole
            // currency units; a payroll line may override it.
            $table->bigInteger('overtime_hourly_rate')->nullable();

            $table->timestamps();
        });

        /*
         * Days the company does not work, beyond the weekend — Eid, Victory
         * Day, a factory shutdown.
         */
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date');
            $table->string('name');
            $table->timestamps();

            // One holiday per day. Two names for the same date is a mistake,
            // not a fact, and the second insert should say so.
            $table->unique(['team_id', 'date']);
        });

        /*
         * One mark, for one person, on one day.
         *
         * Deliberately **not** soft-deleting. "No mark" is a real state the
         * grid must be able to return to — for a salaried employee it means
         * "nothing exceptional happened" — and a soft-deleted row would sit
         * under the unique index blocking the day from ever being marked
         * again. Unmarking a cell deletes the row.
         */
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();

            $table->date('date');

            // See App\Enums\AttendanceStatus. A string so a stale row keeps its
            // meaning if the enum grows.
            $table->string('status');

            $table->string('note')->nullable();
            $table->timestamps();

            // The grid cell's identity, and what `upsert()` keys on: saving the
            // same cell twice changes it rather than duplicating it.
            $table->unique(['employee_id', 'date']);

            // The month query: every screen here asks for one company's marks
            // between two dates.
            $table->index(['team_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('payroll_settings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * How much of an advance a payroll run recovered.
         *
         * Bookkeeping for the schedule and nothing else — **never** a ledger
         * line. The advance itself was the money leaving; withholding part of a
         * later month's net moves nothing, and counting it again would show
         * somebody paying for the same advance twice.
         *
         * Its only job is to let ReplayAdvanceOutstanding derive what is left,
         * so reopening an approved run gives the outstanding back.
         */
        Schema::create('advance_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            // The advance being recovered.
            $table->foreignId('salary_payment_id')->constrained()->cascadeOnDelete();

            // The line that recovered it. Cascades, so reopening a run — which
            // deletes and rebuilds its lines — hands the outstanding back
            // without a second statement.
            $table->foreignId('payroll_line_id')->nullable()->constrained()->cascadeOnDelete();

            $table->date('repaid_on');
            $table->bigInteger('amount');
            $table->timestamps();

            $table->index(['team_id', 'salary_payment_id']);
        });

        /*
         * A bonus somebody was awarded — Eid, performance, or a one-off.
         *
         * Deliberately **no** `payroll_run_id`. A run folds in every bonus
         * whose `awarded_on` falls inside its month, derived on each recompute,
         * so backdating an Eid bonus corrects the month it belongs to instead
         * of needing the run to be rebuilt by hand.
         *
         * Awarding is not paying: this is the entitlement, and the money leaves
         * through `salary_payments` with `kind = bonus` like everything else.
         */
        Schema::create('employee_bonuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('bonus_type');
            $table->date('awarded_on');
            $table->bigInteger('amount');
            $table->string('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'awarded_on']);
            $table->index(['team_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_bonuses');
        Schema::dropIfExists('advance_repayments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * What somebody is paid, and from when.
         *
         * Effective-dated rather than a column on `employees`, because a raise
         * in June must not rewrite January's payslip. Payroll asks for the rate
         * in force during the month it is computing, so history stays true and
         * a genuine correction — a rate typed wrongly three months ago — is
         * still fixable by editing the row that was wrong.
         *
         * `salary_type` is repeated here because somebody can move from a daily
         * wage to a monthly salary, and the month they moved must compute on
         * the basis that applied then.
         */
        Schema::create('employee_salary_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('salary_type');

            // Per month or per day, per `salary_type`. Whole currency units.
            $table->bigInteger('amount');

            $table->date('effective_from');
            $table->timestamps();

            // Two rates from the same day would make "which applied" a coin toss.
            $table->unique(['employee_id', 'effective_from']);
            $table->index(['team_id', 'employee_id']);
        });

        /*
         * One month's payroll.
         *
         * A draft holds no truth: it is recomputed in full from attendance, the
         * rate in force and the outstanding advances every time it is opened or
         * saved. Approving freezes the figures, because that is the moment a
         * payslip is printed and handed to somebody.
         */
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            // Always the first of the month: a payroll period is a month, and
            // storing a range would invite two runs that overlap.
            $table->date('period_month');

            $table->string('status');
            $table->timestamp('approved_at')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // One run per month, full stop. Two would each pay half the staff
            // and neither would be the month's payroll.
            $table->unique(['team_id', 'period_month']);
            $table->index(['team_id', 'status']);
        });

        /*
         * One person's line on one run.
         *
         * The columns split into two kinds, and the distinction is what makes a
         * recompute safe: **inputs** are typed by a person and survive it,
         * **computed** figures are rewritten from scratch every time.
         */
        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();

            // restrictOnDelete: a line names who was paid, and a payslip with
            // nobody on it is not a record of anything.
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();

            // --- inputs, preserved across a recompute ---
            $table->unsignedInteger('overtime_hours')->default(0);
            $table->bigInteger('overtime_rate')->default(0);
            $table->bigInteger('other_addition')->default(0);
            $table->bigInteger('other_deduction')->default(0);
            $table->string('remarks')->nullable();

            // --- computed, rewritten on every recompute, frozen at approval ---
            $table->string('salary_type');
            $table->bigInteger('rate_applied')->default(0);

            /*
             * Half-days, not days: a half-day worked would otherwise need a
             * second rounding step. `unit_total` is the month's full
             * entitlement and `unit_payable` what was earned of it.
             */
            $table->unsignedInteger('unit_total')->default(0);
            $table->unsignedInteger('unit_payable')->default(0);

            $table->unsignedInteger('present_days')->default(0);
            $table->unsignedInteger('half_days')->default(0);
            $table->unsignedInteger('absent_days')->default(0);
            $table->unsignedInteger('leave_days')->default(0);

            $table->bigInteger('gross_earned')->default(0);
            $table->bigInteger('overtime_amount')->default(0);
            $table->bigInteger('bonus_amount')->default(0);
            $table->bigInteger('advance_deduction')->default(0);
            $table->bigInteger('net_payable')->default(0);

            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
        });

        /*
         * Money leaving the company as wages — **the only** such table.
         *
         * Payroll lines are an entitlement, not a cash movement, so the
         * financial report reads this and nothing else, exactly as it reads
         * `vendor_payments`. Salary, an advance and a bonus payment are all
         * money out of the same door and differ only in `kind`; keeping them in
         * one table means `money()` has one sum to take and cannot
         * double-count.
         */
        Schema::create('salary_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();

            // Informational only: which run this settled, when it settled one.
            // An advance belongs to no run.
            $table->foreignId('payroll_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('kind');

            // A date, not a timestamp: money is paid on a day, and `paid_on`
            // decides which period a report counts it in.
            $table->date('paid_on');

            $table->bigInteger('amount');
            $table->string('comment')->nullable();

            // Only meaningful when kind = advance.
            $table->bigInteger('installment_amount')->nullable();

            /*
             * How much of an advance is still to be recovered. Derived, never
             * typed: written by ReplayAdvanceOutstanding from the repayments,
             * so reopening a run gives the outstanding back.
             */
            $table->bigInteger('outstanding')->default(0);

            $table->timestamps();

            /*
             * Soft deletes are mandatory here, not optional: FinancialAnalytics
             * filters `whereNull('deleted_at')` on every table it buckets, so a
             * table without the column throws on every analytics request. It is
             * also the right behaviour — money recorded as paid is worth being
             * able to recover.
             */
            $table->softDeletes();

            $table->index(['team_id', 'paid_on']);
            $table->index(['team_id', 'employee_id', 'paid_on']);
            $table->index(['team_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_payments');
        Schema::dropIfExists('payroll_lines');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('employee_salary_rates');
    }
};

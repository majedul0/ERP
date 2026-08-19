<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * What a company calls its parts — Sales, Factory, Delivery, Accounts.
         *
         * A table rather than an enum because every company names these
         * differently, and per-tenant means one company adding "Packaging"
         * cannot appear in another's list.
         */
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();

            /*
             * Deliberately not a unique index. Departments soft-delete, and a
             * unique constraint would refuse to re-create a name that had been
             * removed — with no way to see why. Uniqueness among *live* rows is
             * enforced by SaveDepartmentRequest, scoped `whereNull('deleted_at')`
             * the way every other name rule in this app is.
             */
            $table->index(['team_id', 'name']);
        });

        /*
         * Somebody who works here.
         *
         * **Not a user.** Every `User` needs a unique email address and a
         * password because it is a login; a loader or a packer has neither and
         * never signs in. This is a record about a person, the same shape as a
         * Vendor, and the two are kept apart on purpose: adding HR fields to
         * `users` would put a salary on the account of every super admin and
         * every invited accountant too.
         */
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // The company's own staff number, as printed on an ID card. Theirs
            // to choose, so it is a string and unique only within the company.
            $table->string('employee_code');

            $table->string('name');
            $table->string('father_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('nid')->nullable();

            // Free text, unlike department: job titles are endlessly specific
            // ("Senior Delivery Supervisor") and nothing groups by them.
            $table->string('designation')->nullable();

            $table->string('photo_path')->nullable();

            // The same address shape a vendor carries, so one presenter formats
            // both — see Vendor::fullAddress().
            $table->string('address')->nullable();
            $table->string('thana')->nullable();
            $table->string('district')->nullable();
            $table->string('division')->nullable();

            /*
             * Monthly or daily — see App\Enums\SalaryType.
             *
             * The one field that decides how this person's pay is worked out:
             * a monthly salary is reduced by absence, a daily wage is earned
             * per day present. Held here rather than only on the rate row so
             * "who is a daily worker" is answerable without reading history.
             */
            $table->string('salary_type');

            $table->date('joined_on');

            // Null means still employed. A date here stops payroll counting
            // them from that month on, and keeps every past payslip intact.
            $table->date('left_on')->nullable();

            /*
             * What the company still owes this person: salary earned, less what
             * has been paid. Negative means they have drawn more than they have
             * earned — an outstanding advance — exactly as a negative vendor
             * balance means the vendor holds one.
             *
             * Derived, never typed: written only by ReplayEmployeeBalance from
             * the approved payroll lines and the salary payments.
             */
            $table->bigInteger('balance')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // A staff number identifies one person within one company; two
            // companies may both have an "EMP-001".
            $table->unique(['team_id', 'employee_code']);
            $table->index(['team_id', 'name']);
            $table->index(['team_id', 'department_id']);
            // "Who works here now" is the commonest question this table is
            // asked — every payroll run and every attendance grid starts there.
            $table->index(['team_id', 'left_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
        Schema::dropIfExists('departments');
    }
};

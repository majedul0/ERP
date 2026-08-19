<?php

namespace App\Enums;

/**
 * What happened on one person's day.
 *
 * Five states rather than present/absent, because payroll pays three of them
 * differently and a company that cannot say "on approved leave" ends up marking
 * it absent and quietly docking somebody's pay.
 *
 * Everything here is expressed in **half-days**, which is the unit
 * App\Support\PayrollCalculator works in: counting whole days would need a
 * second rounding step the moment somebody works a morning.
 */
enum AttendanceStatus: string
{
    case Present = 'present';
    case HalfDay = 'half_day';

    /** Leave that is paid — earned holiday, sick leave a company honours. */
    case PaidLeave = 'paid_leave';

    /** Leave that is not paid, agreed in advance. */
    case UnpaidLeave = 'unpaid_leave';

    /** Simply not there. */
    case Absent = 'absent';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::HalfDay => 'Half day',
            self::PaidLeave => 'Paid leave',
            self::UnpaidLeave => 'Unpaid leave',
            self::Absent => 'Absent',
        };
    }

    /**
     * The single letter in a grid cell.
     */
    public function initial(): string
    {
        return match ($this) {
            self::Present => 'P',
            self::HalfDay => 'H',
            self::PaidLeave => 'L',
            self::UnpaidLeave => 'U',
            self::Absent => 'A',
        };
    }

    /**
     * How many half-days of pay this state earns, out of two.
     *
     * Paid leave earns the day: that is what makes it paid. Unpaid leave and
     * absence earn nothing, and differ only in whether it was agreed — which
     * matters to the person's record, not to the arithmetic.
     */
    public function payableHalfDays(): int
    {
        return match ($this) {
            self::Present, self::PaidLeave => 2,
            self::HalfDay => 1,
            self::UnpaidLeave, self::Absent => 0,
        };
    }

    /**
     * Whether this state counts as the person having turned up at all.
     *
     * Used by the attendance summary, not by pay — paid leave earns a day's
     * money without being a day worked, and the summary should not claim they
     * were here.
     */
    public function isAttendance(): bool
    {
        return $this === self::Present || $this === self::HalfDay;
    }

    /**
     * @return array<int, array{value: string, label: string, initial: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'initial' => $status->initial(),
            ],
            self::cases(),
        );
    }
}

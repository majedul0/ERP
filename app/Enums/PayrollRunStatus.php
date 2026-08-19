<?php

namespace App\Enums;

/**
 * Whether a month's payroll is still being worked out, or has been agreed.
 *
 * Two states, because there are only two questions worth asking of a run: may
 * the figures still move, and has anybody been handed a payslip from it.
 *
 * A **draft** holds no truth. It is recomputed in full from attendance, the
 * rate in force and the outstanding advances every time it is opened, so
 * correcting a mark simply corrects the run.
 *
 * **Approved** freezes the figures, because that is the moment a payslip is
 * printed and given to somebody, and a document that changes after it is handed
 * over is not a document. An approved run whose attendance later moved is
 * flagged on screen rather than silently rewritten — see App\Support\PayrollCalculator.
 */
enum PayrollRunStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Approved',
        };
    }

    /** Whether the figures on this run may still be recomputed. */
    public function isOpen(): bool
    {
        return $this === self::Draft;
    }
}

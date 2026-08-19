import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatAmount, formatMoney } from '@/lib/format';
import { useCompanyBrand } from '@/modules/company';
import type { Payslip } from '@/modules/hr';
import { show } from '@/routes/payroll';

function Row({
    label,
    value,
    strong = false,
}: {
    label: string;
    value: string;
    strong?: boolean;
}) {
    return (
        <div
            className={`flex justify-between gap-4 py-1 ${
                strong ? 'border-t border-coffee-300 font-bold' : ''
            }`}
        >
            <dt className={strong ? 'text-coffee-900' : 'text-coffee-800/70'}>
                {label}
            </dt>
            <dd className="text-coffee-900 tabular-nums">{value}</dd>
        </div>
    );
}

/**
 * Every payslip on a run, one per page when printed.
 *
 * Rendered from the run's frozen figures rather than recomputed — a payslip is
 * a document somebody was handed, and reprinting it has to produce the same
 * paper. Drift against today's data is flagged on the run screen instead.
 */
export default function Payslips({
    run,
    payslips,
}: {
    run: { id: number; monthLabel: string; status: string };
    payslips: Payslip[];
}) {
    const brand = useCompanyBrand();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const money = (amount: number) => formatMoney(amount, brand.currencySymbol);

    return (
        <>
            <Head title={`Payslips — ${run.monthLabel}`} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
                <div>
                    <h1 className="text-xl font-bold text-coffee-900">
                        Payslips — {run.monthLabel}
                    </h1>
                    {run.status === 'draft' && (
                        <p className="text-sm text-amber-700">
                            This month is still a draft. Its figures will change
                            every time it is recalculated.
                        </p>
                    )}
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button asChild variant="outline">
                        <Link
                            href={show({ current_team: teamSlug, run: run.id })}
                        >
                            Back
                        </Link>
                    </Button>
                    <Button
                        onClick={() => window.print()}
                        className="bg-coffee-600 hover:bg-coffee-700"
                    >
                        Print all
                    </Button>
                </div>
            </div>

            <div className="space-y-6">
                {payslips.length === 0 && (
                    <p className="py-10 text-center text-coffee-800/60">
                        Nobody was on this payroll.
                    </p>
                )}

                {payslips.map((slip) => (
                    <article
                        key={slip.employeeId}
                        className="rounded-lg border border-coffee-100 bg-white p-6 shadow-sm print:break-after-page print:rounded-none print:border-0 print:shadow-none"
                    >
                        <header className="mb-4 border-b border-coffee-200 pb-3 text-center">
                            <h2 className="text-lg font-bold text-coffee-900">
                                {brand.name}
                            </h2>
                            <p className="text-sm text-coffee-800/70">
                                Payslip — {run.monthLabel}
                            </p>
                        </header>

                        <div className="mb-4 grid gap-1 text-sm sm:grid-cols-2">
                            <p className="text-coffee-900">
                                <span className="text-coffee-800/60">
                                    Name:{' '}
                                </span>
                                <strong>{slip.employeeName}</strong>
                            </p>
                            <p className="text-coffee-900">
                                <span className="text-coffee-800/60">
                                    Staff No.:{' '}
                                </span>
                                {slip.employeeCode}
                            </p>
                            <p className="text-coffee-900">
                                <span className="text-coffee-800/60">
                                    Designation:{' '}
                                </span>
                                {slip.designation ?? '—'}
                            </p>
                            <p className="text-coffee-900">
                                <span className="text-coffee-800/60">
                                    Department:{' '}
                                </span>
                                {slip.departmentName ?? '—'}
                            </p>
                        </div>

                        <div className="grid gap-6 sm:grid-cols-2">
                            <dl className="text-sm">
                                <p className="mb-1 text-xs font-semibold tracking-wide text-coffee-800/60 uppercase">
                                    Earnings
                                </p>
                                <Row
                                    label={slip.salaryTypeLabel}
                                    value={money(slip.rateApplied)}
                                />
                                <Row
                                    label="Earned for the month"
                                    value={money(slip.grossEarned)}
                                />
                                {slip.overtimeAmount > 0 && (
                                    <Row
                                        label={`Overtime (${formatAmount(slip.overtimeHours)} hrs)`}
                                        value={money(slip.overtimeAmount)}
                                    />
                                )}
                                {slip.bonusAmount > 0 && (
                                    <Row
                                        label="Bonus"
                                        value={money(slip.bonusAmount)}
                                    />
                                )}
                                {slip.otherAddition > 0 && (
                                    <Row
                                        label="Other additions"
                                        value={money(slip.otherAddition)}
                                    />
                                )}
                            </dl>

                            <dl className="text-sm">
                                <p className="mb-1 text-xs font-semibold tracking-wide text-coffee-800/60 uppercase">
                                    Deductions
                                </p>
                                {slip.absenceDeduction > 0 && (
                                    <Row
                                        label="Absence"
                                        value={money(slip.absenceDeduction)}
                                    />
                                )}
                                {slip.advanceDeduction > 0 && (
                                    <Row
                                        label="Advance recovered"
                                        value={money(slip.advanceDeduction)}
                                    />
                                )}
                                {slip.otherDeduction > 0 && (
                                    <Row
                                        label="Other deductions"
                                        value={money(slip.otherDeduction)}
                                    />
                                )}
                                <div className="mt-3">
                                    <p className="mb-1 text-xs font-semibold tracking-wide text-coffee-800/60 uppercase">
                                        Attendance
                                    </p>
                                    <Row
                                        label="Present"
                                        value={formatAmount(slip.presentDays)}
                                    />
                                    <Row
                                        label="Half days"
                                        value={formatAmount(slip.halfDays)}
                                    />
                                    <Row
                                        label="Leave"
                                        value={formatAmount(slip.leaveDays)}
                                    />
                                    <Row
                                        label="Absent"
                                        value={formatAmount(slip.absentDays)}
                                    />
                                </div>
                            </dl>
                        </div>

                        <dl className="mt-4 text-base">
                            <Row
                                label="Net payable"
                                value={money(slip.netPayable)}
                                strong
                            />
                        </dl>

                        {slip.remarks && (
                            <p className="mt-3 text-xs text-coffee-800/70">
                                {slip.remarks}
                            </p>
                        )}

                        <div className="mt-10 flex justify-between text-xs text-coffee-800/60">
                            <span>Employee signature</span>
                            <span>Authorised signature</span>
                        </div>
                    </article>
                ))}
            </div>
        </>
    );
}

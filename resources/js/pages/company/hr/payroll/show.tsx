import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatAmount, formatMoney } from '@/lib/format';
import { useCan, useCompanyBrand } from '@/modules/company';
import type { PayrollLineRow, PayrollRunDetail } from '@/modules/hr';
import { approve, index, reopen, update } from '@/routes/payroll';
import { index as payslipsIndex } from '@/routes/payslips';

const headCell =
    'bg-coffee-500 px-3 py-3 text-left text-xs font-bold tracking-wide text-white uppercase whitespace-nowrap';
const bodyCell = 'px-3 py-2 whitespace-nowrap text-coffee-900';
const numberCell = `${bodyCell} text-right tabular-nums`;

type Draft = Record<
    number,
    {
        overtime_hours: number;
        overtime_rate: number;
        other_addition: number;
        other_deduction: number;
    }
>;

export default function PayrollRun({
    run,
    lines,
    driftedEmployeeIds,
    paidTotal,
}: {
    run: PayrollRunDetail;
    lines: PayrollLineRow[];
    driftedEmployeeIds: number[];
    paidTotal: number;
}) {
    const brand = useCompanyBrand();
    const can = useCan();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const manages = can('payroll:manage');
    const money = (amount: number) => formatMoney(amount, brand.currencySymbol);

    const isDraft = run.status === 'draft';
    const drifted = new Set(driftedEmployeeIds);

    const [draft, setDraft] = useState<Draft>(() =>
        Object.fromEntries(
            lines.map((line) => [
                line.employeeId,
                {
                    overtime_hours: line.overtimeHours,
                    overtime_rate: line.overtimeRate,
                    other_addition: line.otherAddition,
                    other_deduction: line.otherDeduction,
                },
            ]),
        ),
    );

    const edit = (
        employeeId: number,
        field: keyof Draft[number],
        value: string,
    ) =>
        setDraft((current) => ({
            ...current,
            [employeeId]: {
                ...current[employeeId],
                [field]: value === '' ? 0 : Number(value),
            },
        }));

    const recalculate = () =>
        router.put(
            update({ current_team: teamSlug, run: run.id }).url,
            {
                lines: lines.map((line) => ({
                    employee_id: line.employeeId,
                    ...draft[line.employeeId],
                })),
            },
            { preserveScroll: true },
        );

    const totals = lines.reduce(
        (running, line) => ({
            gross: running.gross + line.grossEarned,
            overtime: running.overtime + line.overtimeAmount,
            bonus: running.bonus + line.bonusAmount,
            advance: running.advance + line.advanceDeduction,
            net: running.net + line.netPayable,
            paid: running.paid + line.paid,
        }),
        { gross: 0, overtime: 0, bonus: 0, advance: 0, net: 0, paid: 0 },
    );

    const outstanding = totals.net - paidTotal;

    return (
        <>
            <Head title={`Payroll — ${run.monthLabel}`} />

            <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-coffee-900">
                        Payroll — {run.monthLabel}
                    </h1>
                    <p className="text-sm text-coffee-800/60">
                        <span
                            className={`rounded px-2 py-0.5 text-xs font-semibold ${
                                isDraft
                                    ? 'bg-amber-100 text-amber-900'
                                    : 'bg-emerald-100 text-emerald-900'
                            }`}
                        >
                            {run.statusLabel}
                        </span>
                        {run.approvedAt &&
                            ` · approved ${run.approvedAt}${run.approvedBy ? ` by ${run.approvedBy}` : ''}`}
                    </p>
                    {!isDraft && (
                        <p className="mt-1 text-sm text-coffee-800/70">
                            {money(paidTotal)} paid
                            {outstanding > 0
                                ? ` · ${money(outstanding)} still to hand over`
                                : ' · fully paid'}
                        </p>
                    )}
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button asChild variant="outline">
                        <Link href={index(teamSlug)}>Back</Link>
                    </Button>

                    <Button asChild variant="outline">
                        <Link
                            href={payslipsIndex({
                                current_team: teamSlug,
                                run: run.id,
                            })}
                        >
                            Payslips
                        </Link>
                    </Button>

                    {manages && isDraft && (
                        <>
                            <Button variant="outline" onClick={recalculate}>
                                Recalculate
                            </Button>
                            <Button
                                onClick={() =>
                                    router.post(
                                        approve({
                                            current_team: teamSlug,
                                            run: run.id,
                                        }).url,
                                    )
                                }
                                className="bg-coffee-600 hover:bg-coffee-700"
                                data-test="approve-payroll"
                            >
                                Approve
                            </Button>
                        </>
                    )}

                    {manages && !isDraft && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    reopen({
                                        current_team: teamSlug,
                                        run: run.id,
                                    }).url,
                                )
                            }
                        >
                            Reopen
                        </Button>
                    )}
                </div>
            </div>

            {drifted.size > 0 && (
                <p className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <strong>{drifted.size} line(s) no longer match</strong> what
                    the attendance and rates say today. The figures below are
                    what was approved and handed out — reopen the month if they
                    need correcting.
                </p>
            )}

            <div className="overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[72rem] text-sm">
                        <thead>
                            <tr>
                                <th className={headCell}>Employee</th>
                                <th className={`${headCell} text-right`}>
                                    Rate
                                </th>
                                <th className={`${headCell} text-right`}>P</th>
                                <th className={`${headCell} text-right`}>A</th>
                                <th className={`${headCell} text-right`}>
                                    Gross
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Absence
                                </th>
                                <th className={`${headCell} text-right`}>
                                    OT hrs
                                </th>
                                <th className={`${headCell} text-right`}>
                                    OT rate
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Bonus
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Add
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Deduct
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Advance
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Net
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Paid
                                </th>
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-coffee-100">
                            {lines.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={14}
                                        className="px-4 py-10 text-center text-coffee-800/60"
                                    >
                                        Nobody was employed during this month.
                                    </td>
                                </tr>
                            )}

                            {lines.map((line) => (
                                <tr
                                    key={line.employeeId}
                                    className={
                                        drifted.has(line.employeeId)
                                            ? 'bg-amber-50/60'
                                            : 'transition-colors hover:bg-coffee-50/60'
                                    }
                                >
                                    <td className={`${bodyCell} font-medium`}>
                                        {line.employeeName}
                                        <span className="ml-2 text-xs font-normal text-coffee-800/50">
                                            {line.employeeCode}
                                        </span>
                                    </td>
                                    <td className={numberCell}>
                                        {money(line.rateApplied)}
                                    </td>
                                    <td className={numberCell}>
                                        {formatAmount(line.presentDays)}
                                    </td>
                                    <td className={numberCell}>
                                        {formatAmount(line.absentDays)}
                                    </td>
                                    <td className={numberCell}>
                                        {money(line.grossEarned)}
                                    </td>
                                    <td className={numberCell}>
                                        {money(line.absenceDeduction)}
                                    </td>

                                    {/* The typed inputs. Everything else on the
                                        row is derived and rewritten on each
                                        recalculation. */}
                                    <td className={numberCell}>
                                        {manages && isDraft ? (
                                            <Input
                                                type="number"
                                                min={0}
                                                className="h-8 w-16 text-right"
                                                value={
                                                    draft[line.employeeId]
                                                        ?.overtime_hours ?? 0
                                                }
                                                onChange={(event) =>
                                                    edit(
                                                        line.employeeId,
                                                        'overtime_hours',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        ) : (
                                            formatAmount(line.overtimeHours)
                                        )}
                                    </td>
                                    <td className={numberCell}>
                                        {manages && isDraft ? (
                                            <Input
                                                type="number"
                                                min={0}
                                                className="h-8 w-20 text-right"
                                                value={
                                                    draft[line.employeeId]
                                                        ?.overtime_rate ?? 0
                                                }
                                                onChange={(event) =>
                                                    edit(
                                                        line.employeeId,
                                                        'overtime_rate',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        ) : (
                                            money(line.overtimeRate)
                                        )}
                                    </td>
                                    <td className={numberCell}>
                                        {money(line.bonusAmount)}
                                    </td>
                                    <td className={numberCell}>
                                        {manages && isDraft ? (
                                            <Input
                                                type="number"
                                                min={0}
                                                className="h-8 w-20 text-right"
                                                value={
                                                    draft[line.employeeId]
                                                        ?.other_addition ?? 0
                                                }
                                                onChange={(event) =>
                                                    edit(
                                                        line.employeeId,
                                                        'other_addition',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        ) : (
                                            money(line.otherAddition)
                                        )}
                                    </td>
                                    <td className={numberCell}>
                                        {manages && isDraft ? (
                                            <Input
                                                type="number"
                                                min={0}
                                                className="h-8 w-20 text-right"
                                                value={
                                                    draft[line.employeeId]
                                                        ?.other_deduction ?? 0
                                                }
                                                onChange={(event) =>
                                                    edit(
                                                        line.employeeId,
                                                        'other_deduction',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        ) : (
                                            money(line.otherDeduction)
                                        )}
                                    </td>
                                    <td className={numberCell}>
                                        {money(line.advanceDeduction)}
                                    </td>
                                    <td className={`${numberCell} font-bold`}>
                                        {money(line.netPayable)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>

                        {lines.length > 0 && (
                            <tfoot>
                                <tr className="border-t-2 border-coffee-200 bg-coffee-50 font-bold">
                                    <td className={bodyCell} colSpan={4}>
                                        Total
                                    </td>
                                    <td className={numberCell}>
                                        {money(totals.gross)}
                                    </td>
                                    <td className={numberCell} colSpan={3} />
                                    <td className={numberCell}>
                                        {money(totals.bonus)}
                                    </td>
                                    <td className={numberCell} colSpan={2} />
                                    <td className={numberCell}>
                                        {money(totals.advance)}
                                    </td>
                                    <td className={numberCell}>
                                        {money(totals.net)}
                                    </td>
                                    <td className={numberCell}>
                                        {money(paidTotal)}
                                    </td>
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>
            </div>

            <p className="mt-4 text-xs text-coffee-800/60">
                Approving freezes these figures, because that is when a payslip
                is handed over. An advance is recovered out of the net, not out
                of what was earned — the money already reached them when the
                advance was paid. The Paid column fills in from the salary
                payments recorded against this month.
            </p>
        </>
    );
}

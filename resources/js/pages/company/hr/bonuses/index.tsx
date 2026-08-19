import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import BonusController from '@/actions/App/Http/Controllers/Hr/BonusController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatMoney, formatSaleDate } from '@/lib/format';
import { useCan, useCompanyBrand } from '@/modules/company';
import type { BonusRow } from '@/modules/hr';
import { index as payrollIndex } from '@/routes/payroll';

const selectClasses =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none';

const headCell =
    'bg-coffee-500 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase whitespace-nowrap';
const bodyCell = 'px-4 py-3 whitespace-nowrap text-coffee-900';

/**
 * Bonuses awarded.
 *
 * Awarding is not paying: a bonus is folded into the payroll run for the month
 * it is dated in, and the money leaves through a salary payment like everything
 * else.
 */
export default function Bonuses({
    bonuses,
    employees,
    bonusTypes,
    total,
}: {
    bonuses: BonusRow[];
    employees: Array<{ id: number; name: string; employeeCode: string }>;
    bonusTypes: Array<{ value: string; label: string }>;
    total: number;
}) {
    const brand = useCompanyBrand();
    const can = useCan();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const manages = can('payroll:manage');
    const money = (amount: number) => formatMoney(amount, brand.currencySymbol);

    return (
        <>
            <Head title="Bonuses" />

            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-coffee-900">
                        Bonuses
                    </h1>
                    <p className="text-sm text-coffee-800/60">
                        {money(total)} awarded
                    </p>
                </div>
                <Button asChild variant="outline">
                    <Link href={payrollIndex(teamSlug)}>Payroll</Link>
                </Button>
            </div>

            {manages && employees.length > 0 && (
                <Form
                    {...BonusController.store.form(teamSlug)}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="mb-6 rounded-lg border border-coffee-100 bg-white p-4 shadow-sm"
                >
                    {({ processing, errors }) => (
                        <div className="grid items-end gap-3 sm:grid-cols-5">
                            <div className="grid gap-1.5">
                                <Label htmlFor="employee_id">Employee</Label>
                                <select
                                    id="employee_id"
                                    name="employee_id"
                                    className={selectClasses}
                                    required
                                >
                                    {employees.map((employee) => (
                                        <option
                                            key={employee.id}
                                            value={employee.id}
                                        >
                                            {employee.name} (
                                            {employee.employeeCode})
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.employee_id} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="bonus_type">Type</Label>
                                <select
                                    id="bonus_type"
                                    name="bonus_type"
                                    className={selectClasses}
                                    required
                                >
                                    {bonusTypes.map((type) => (
                                        <option
                                            key={type.value}
                                            value={type.value}
                                        >
                                            {type.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.bonus_type} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="awarded_on">Awarded on</Label>
                                <Input
                                    id="awarded_on"
                                    name="awarded_on"
                                    type="date"
                                    required
                                />
                                <InputError message={errors.awarded_on} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="amount">Amount</Label>
                                <Input
                                    id="amount"
                                    name="amount"
                                    type="number"
                                    min={1}
                                    step={1}
                                    required
                                    data-test="bonus-amount"
                                />
                                <InputError message={errors.amount} />
                            </div>

                            <Button
                                type="submit"
                                disabled={processing}
                                data-test="add-bonus"
                                className="bg-coffee-600 hover:bg-coffee-700"
                            >
                                Award
                            </Button>
                        </div>
                    )}
                </Form>
            )}

            <div className="overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[44rem] text-sm">
                        <thead>
                            <tr>
                                <th className={headCell}>Awarded</th>
                                <th className={headCell}>Employee</th>
                                <th className={headCell}>Type</th>
                                <th className={`${headCell} text-right`}>
                                    Amount
                                </th>
                                <th className={headCell}>
                                    <span className="sr-only">Remove</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-coffee-100">
                            {bonuses.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-coffee-800/60"
                                    >
                                        No bonuses awarded yet.
                                    </td>
                                </tr>
                            )}

                            {bonuses.map((bonus) => (
                                <tr
                                    key={bonus.id}
                                    className="transition-colors hover:bg-coffee-50/60"
                                >
                                    <td className={bodyCell}>
                                        {formatSaleDate(bonus.awardedOn)}
                                    </td>
                                    <td className={`${bodyCell} font-medium`}>
                                        {bonus.employeeName}
                                        <span className="ml-2 text-xs font-normal text-coffee-800/50">
                                            {bonus.employeeCode}
                                        </span>
                                    </td>
                                    <td className={bodyCell}>
                                        {bonus.bonusTypeLabel}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right font-medium tabular-nums`}
                                    >
                                        {money(bonus.amount)}
                                    </td>
                                    <td className={bodyCell}>
                                        {manages && (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="text-red-700 hover:text-red-800"
                                                onClick={() =>
                                                    router.delete(
                                                        BonusController.destroy.url(
                                                            {
                                                                current_team:
                                                                    teamSlug,
                                                                bonus: bonus.id,
                                                            },
                                                        ),
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                Remove
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <p className="mt-4 text-xs text-coffee-800/60">
                A bonus lands on the payroll run for the month it is dated in,
                so backdating one corrects that month rather than the current
                one.
            </p>
        </>
    );
}

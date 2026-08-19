import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import SalaryRateController from '@/actions/App/Http/Controllers/Hr/SalaryRateController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatMoney, formatSaleDate } from '@/lib/format';
import { useCan, useCompanyBrand } from '@/modules/company';
import type { RatedEmployee, SalaryTypeOption } from '@/modules/hr';
import { index as payrollIndex } from '@/routes/payroll';

const selectClasses =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none';

/**
 * What each person is paid, and from when.
 *
 * Effective-dated on purpose: a raise in June leaves January's payslip exactly
 * as it was printed, and correcting a rate typed wrongly means editing the row
 * that was wrong rather than rewriting history.
 */
export default function SalaryRates({
    employees,
    salaryTypes,
}: {
    employees: RatedEmployee[];
    salaryTypes: SalaryTypeOption[];
}) {
    const brand = useCompanyBrand();
    const can = useCan();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const manages = can('payroll:manage');
    const money = (amount: number) => formatMoney(amount, brand.currencySymbol);

    const remove = (id: number) =>
        router.delete(
            SalaryRateController.destroy.url({
                current_team: teamSlug,
                rate: id,
            }),
            { preserveScroll: true },
        );

    return (
        <>
            <Head title="Salary rates" />

            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-xl font-bold text-coffee-900">
                    Salary rates
                </h1>
                <Button asChild variant="outline">
                    <Link href={payrollIndex(teamSlug)}>Payroll</Link>
                </Button>
            </div>

            {manages && (
                <Form
                    {...SalaryRateController.store.form(teamSlug)}
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
                                <Label htmlFor="salary_type">Basis</Label>
                                <select
                                    id="salary_type"
                                    name="salary_type"
                                    className={selectClasses}
                                    required
                                >
                                    {salaryTypes.map((type) => (
                                        <option
                                            key={type.value}
                                            value={type.value}
                                        >
                                            {type.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.salary_type} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="amount">Amount</Label>
                                <Input
                                    id="amount"
                                    name="amount"
                                    type="number"
                                    min={0}
                                    step={1}
                                    required
                                    data-test="rate-amount"
                                />
                                <InputError message={errors.amount} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="effective_from">
                                    Effective from
                                </Label>
                                <Input
                                    id="effective_from"
                                    name="effective_from"
                                    type="date"
                                    required
                                />
                                <InputError message={errors.effective_from} />
                            </div>

                            <Button
                                type="submit"
                                disabled={processing}
                                data-test="add-rate"
                                className="bg-coffee-600 hover:bg-coffee-700"
                            >
                                Save rate
                            </Button>
                        </div>
                    )}
                </Form>
            )}

            <div className="space-y-3">
                {employees.map((employee) => (
                    <div
                        key={employee.id}
                        className="rounded-lg border border-coffee-100 bg-white p-4 shadow-sm"
                    >
                        <div className="mb-2 flex flex-wrap items-baseline gap-2">
                            <h2 className="font-bold text-coffee-900">
                                {employee.name}
                            </h2>
                            <span className="text-xs text-coffee-800/50">
                                {employee.employeeCode}
                            </span>
                            {!employee.isActive && (
                                <span className="rounded bg-coffee-100 px-1.5 py-0.5 text-xs text-coffee-800/70">
                                    Left
                                </span>
                            )}
                        </div>

                        {employee.rates.length === 0 ? (
                            <p className="text-sm text-amber-700">
                                No rate set — payroll will compute nothing for
                                this person.
                            </p>
                        ) : (
                            <ul className="divide-y divide-coffee-100 text-sm">
                                {employee.rates.map((rate, position) => (
                                    <li
                                        key={rate.id}
                                        className="flex flex-wrap items-center gap-3 py-1.5"
                                    >
                                        <span className="w-28 text-coffee-800/70 tabular-nums">
                                            {formatSaleDate(rate.effectiveFrom)}
                                        </span>
                                        <span className="font-medium text-coffee-900 tabular-nums">
                                            {money(rate.amount)}
                                        </span>
                                        <span className="text-coffee-800/60">
                                            {rate.salaryTypeLabel}
                                        </span>
                                        {position === 0 && (
                                            <span className="rounded bg-emerald-100 px-1.5 py-0.5 text-xs text-emerald-900">
                                                Current
                                            </span>
                                        )}
                                        {manages && (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="ml-auto text-red-700 hover:text-red-800"
                                                onClick={() => remove(rate.id)}
                                            >
                                                Remove
                                            </Button>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                ))}
            </div>

            <p className="mt-4 text-xs text-coffee-800/60">
                A payroll run uses the latest rate dated on or before the last
                day of its month, so a raise dated later never changes a month
                already worked.
            </p>
        </>
    );
}

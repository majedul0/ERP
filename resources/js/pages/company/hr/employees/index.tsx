import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatSaleDate } from '@/lib/format';
import { useCan } from '@/modules/company';
import type { EmployeeRow } from '@/modules/hr';
import { index as departmentsIndex } from '@/routes/departments';
import { create, edit, show } from '@/routes/employees';

const headCell =
    'bg-coffee-500 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase whitespace-nowrap';
const bodyCell = 'px-4 py-3 whitespace-nowrap text-coffee-900';

export default function Employees({
    employees,
    activeCount,
}: {
    employees: EmployeeRow[];
    activeCount: number;
}) {
    const can = useCan();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const manages = can('employee:manage');

    const [search, setSearch] = useState('');
    const [showLeft, setShowLeft] = useState(false);

    const term = search.trim().toLowerCase();

    const visible = employees.filter((employee) => {
        if (!showLeft && !employee.isActive) {
            return false;
        }

        if (term === '') {
            return true;
        }

        return [
            employee.name,
            employee.employeeCode,
            employee.designation ?? '',
            employee.departmentName ?? '',
            employee.phone ?? '',
        ].some((field) => field.toLowerCase().includes(term));
    });

    return (
        <>
            <Head title="Employees" />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-coffee-900">
                        Employees
                    </h1>
                    <p className="text-sm text-coffee-800/60">
                        {activeCount} working here
                        {employees.length > activeCount &&
                            `, ${employees.length - activeCount} left`}
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button asChild variant="outline">
                        <Link href={departmentsIndex(teamSlug)}>
                            Departments
                        </Link>
                    </Button>

                    {manages && (
                        <Button
                            asChild
                            className="bg-coffee-600 hover:bg-coffee-700"
                        >
                            <Link href={create(teamSlug)}>+ Add Employee</Link>
                        </Button>
                    )}
                </div>
            </div>

            <div className="mb-4 flex flex-wrap items-center gap-3">
                <Input
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Search by name, staff number, designation…"
                    className="max-w-xs"
                    aria-label="Search employees"
                />

                <label className="flex items-center gap-2 text-sm text-coffee-800/70">
                    <input
                        type="checkbox"
                        checked={showLeft}
                        onChange={(event) => setShowLeft(event.target.checked)}
                        className="size-4 rounded border-coffee-300"
                    />
                    Include people who have left
                </label>
            </div>

            <div className="overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[60rem] text-sm">
                        <thead>
                            <tr>
                                <th className={headCell}>Photo</th>
                                <th className={headCell}>Staff No.</th>
                                <th className={headCell}>Name</th>
                                <th className={headCell}>Designation</th>
                                <th className={headCell}>Department</th>
                                <th className={headCell}>Phone</th>
                                <th className={headCell}>Paid</th>
                                <th className={headCell}>Joined</th>
                                <th className={headCell}>
                                    <span className="sr-only">Open</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-coffee-100">
                            {visible.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={9}
                                        className="px-4 py-10 text-center text-coffee-800/60"
                                    >
                                        {employees.length === 0
                                            ? 'Nobody has been added yet.'
                                            : 'No employee matches that search.'}
                                    </td>
                                </tr>
                            )}

                            {visible.map((employee) => (
                                <tr
                                    key={employee.id}
                                    className="transition-colors hover:bg-coffee-50/60"
                                >
                                    <td className={bodyCell}>
                                        {employee.photoUrl ? (
                                            <img
                                                src={employee.photoUrl}
                                                alt=""
                                                className="size-10 rounded-md object-cover"
                                            />
                                        ) : (
                                            <div className="size-10 rounded-md bg-coffee-50" />
                                        )}
                                    </td>
                                    <td className={bodyCell}>
                                        {employee.employeeCode}
                                    </td>
                                    <td className={`${bodyCell} font-medium`}>
                                        {employee.name}
                                        {!employee.isActive && (
                                            <span className="ml-2 rounded bg-coffee-100 px-1.5 py-0.5 text-xs font-normal text-coffee-800/70">
                                                Left{' '}
                                                {employee.leftOn &&
                                                    formatSaleDate(
                                                        employee.leftOn,
                                                    )}
                                            </span>
                                        )}
                                    </td>
                                    <td className={bodyCell}>
                                        {employee.designation ?? '—'}
                                    </td>
                                    <td className={bodyCell}>
                                        {employee.departmentName ?? '—'}
                                    </td>
                                    <td className={bodyCell}>
                                        {employee.phone ?? '—'}
                                    </td>
                                    <td className={bodyCell}>
                                        {employee.salaryTypeLabel}
                                    </td>
                                    <td className={bodyCell}>
                                        {formatSaleDate(employee.joinedOn)}
                                    </td>
                                    <td className={bodyCell}>
                                        <div className="flex gap-3">
                                            <Link
                                                href={show({
                                                    current_team: teamSlug,
                                                    employee: employee.id,
                                                })}
                                                className="text-coffee-700 underline underline-offset-4 hover:text-coffee-900"
                                            >
                                                Open
                                            </Link>
                                            {manages && (
                                                <Link
                                                    href={edit({
                                                        current_team: teamSlug,
                                                        employee: employee.id,
                                                    })}
                                                    className="text-coffee-700 underline underline-offset-4 hover:text-coffee-900"
                                                >
                                                    Edit
                                                </Link>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

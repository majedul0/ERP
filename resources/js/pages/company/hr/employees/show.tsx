import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatSaleDate } from '@/lib/format';
import { useCan } from '@/modules/company';
import type { EmployeeDetail } from '@/modules/hr';
import { DeleteEmployeeDialog } from '@/modules/hr';
import { edit, index } from '@/routes/employees';

function Row({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="flex justify-between gap-4 border-b border-coffee-100 py-2.5 last:border-0">
            <dt className="text-coffee-800/70">{label}</dt>
            <dd className="text-right font-medium text-coffee-900">
                {value === null || value === '' ? '—' : value}
            </dd>
        </div>
    );
}

export default function EmployeeRecord({
    employee,
}: {
    employee: EmployeeDetail;
}) {
    const can = useCan();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const manages = can('employee:manage');

    return (
        <>
            <Head title={employee.name} />

            <div className="mx-auto w-full max-w-3xl">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div className="flex items-center gap-4">
                        {employee.photoUrl ? (
                            <img
                                src={employee.photoUrl}
                                alt=""
                                className="size-16 rounded-lg object-cover"
                            />
                        ) : (
                            <div className="size-16 rounded-lg bg-coffee-50" />
                        )}
                        <div>
                            <h1 className="text-2xl font-bold text-coffee-900">
                                {employee.name}
                            </h1>
                            <p className="text-sm text-coffee-800/60">
                                {employee.employeeCode}
                                {employee.designation &&
                                    ` · ${employee.designation}`}
                                {employee.departmentName &&
                                    ` · ${employee.departmentName}`}
                            </p>
                            {!employee.isActive && employee.leftOn && (
                                <p className="mt-1 inline-block rounded bg-coffee-100 px-2 py-0.5 text-xs text-coffee-800/80">
                                    Left on {formatSaleDate(employee.leftOn)}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button asChild variant="outline">
                            <Link href={index(teamSlug)}>Back</Link>
                        </Button>

                        {manages && (
                            <>
                                <Button
                                    asChild
                                    className="bg-coffee-600 hover:bg-coffee-700"
                                >
                                    <Link
                                        href={edit({
                                            current_team: teamSlug,
                                            employee: employee.id,
                                        })}
                                    >
                                        Edit
                                    </Link>
                                </Button>

                                <DeleteEmployeeDialog
                                    teamSlug={teamSlug}
                                    employeeId={employee.id}
                                    name={employee.name}
                                />
                            </>
                        )}
                    </div>
                </div>

                <div className="rounded-lg border border-coffee-100 bg-white p-5 shadow-sm">
                    <h2 className="mb-3 text-base font-bold text-coffee-900">
                        Details
                    </h2>
                    <dl className="text-sm">
                        <Row
                            label="Staff number"
                            value={employee.employeeCode}
                        />
                        <Row
                            label="Father's name"
                            value={employee.fatherName}
                        />
                        <Row label="Phone" value={employee.phone} />
                        <Row label="NID" value={employee.nid} />
                        <Row label="Address" value={employee.fullAddress} />
                        <Row label="Paid" value={employee.salaryTypeLabel} />
                        <Row
                            label="Joined"
                            value={formatSaleDate(employee.joinedOn)}
                        />
                        <Row
                            label="Left"
                            value={
                                employee.leftOn
                                    ? formatSaleDate(employee.leftOn)
                                    : null
                            }
                        />
                    </dl>
                </div>

                {/* Attendance, salary history and the employee's account arrive
                    in the phases after this one; the record screen is where
                    they will hang. */}
            </div>
        </>
    );
}

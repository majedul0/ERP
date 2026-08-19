import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import DepartmentController from '@/actions/App/Http/Controllers/Employees/DepartmentController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCan } from '@/modules/company';
import type { DepartmentRow } from '@/modules/hr';
import { index as employeesIndex } from '@/routes/employees';

/**
 * The company's departments.
 *
 * One screen: the list, an add box, and a rename in place. A department is a
 * name and a count, so it never earned a page of its own.
 */
export default function Departments({
    departments,
}: {
    departments: DepartmentRow[];
}) {
    const can = useCan();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const manages = can('employee:manage');

    const [editing, setEditing] = useState<number | null>(null);
    const [draft, setDraft] = useState('');

    const rename = (department: DepartmentRow) => {
        router.put(
            DepartmentController.update.url({
                current_team: teamSlug,
                department: department.id,
            }),
            { name: draft },
            { preserveScroll: true, onSuccess: () => setEditing(null) },
        );
    };

    const remove = (department: DepartmentRow) => {
        router.delete(
            DepartmentController.destroy.url({
                current_team: teamSlug,
                department: department.id,
            }),
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Departments" />

            <div className="mx-auto w-full max-w-2xl">
                <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-xl font-bold text-coffee-900">
                        Departments
                    </h1>
                    <Button asChild variant="outline">
                        <Link href={employeesIndex(teamSlug)}>Employees</Link>
                    </Button>
                </div>

                {manages && (
                    <Form
                        {...DepartmentController.store.form(teamSlug)}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="mb-6 rounded-lg border border-coffee-100 bg-white p-4 shadow-sm"
                    >
                        {({ processing, errors }) => (
                            <div className="flex items-end gap-3">
                                <div className="grid flex-1 gap-1.5">
                                    <Label
                                        htmlFor="name"
                                        className="text-coffee-900"
                                    >
                                        New department
                                    </Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        placeholder="Delivery"
                                        data-test="department-name"
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="add-department"
                                    className="bg-coffee-600 hover:bg-coffee-700"
                                >
                                    Add
                                </Button>
                            </div>
                        )}
                    </Form>
                )}

                <div className="overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm">
                    <ul className="divide-y divide-coffee-100">
                        {departments.length === 0 && (
                            <li className="px-4 py-10 text-center text-sm text-coffee-800/60">
                                No departments yet. An employee can be added
                                without one.
                            </li>
                        )}

                        {departments.map((department) => (
                            <li
                                key={department.id}
                                className="flex flex-wrap items-center gap-3 px-4 py-3"
                            >
                                {editing === department.id ? (
                                    <>
                                        <Input
                                            value={draft}
                                            onChange={(event) =>
                                                setDraft(event.target.value)
                                            }
                                            className="max-w-xs"
                                            aria-label="Department name"
                                        />
                                        <Button
                                            size="sm"
                                            onClick={() => rename(department)}
                                            className="bg-coffee-600 hover:bg-coffee-700"
                                        >
                                            Save
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => setEditing(null)}
                                        >
                                            Cancel
                                        </Button>
                                    </>
                                ) : (
                                    <>
                                        <span className="font-medium text-coffee-900">
                                            {department.name}
                                        </span>
                                        <span className="text-sm text-coffee-800/60">
                                            {department.employeeCount}{' '}
                                            {department.employeeCount === 1
                                                ? 'person'
                                                : 'people'}
                                        </span>

                                        {manages && (
                                            <div className="ml-auto flex gap-2">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => {
                                                        setEditing(
                                                            department.id,
                                                        );
                                                        setDraft(
                                                            department.name,
                                                        );
                                                    }}
                                                >
                                                    Rename
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="text-red-700 hover:text-red-800"
                                                    onClick={() =>
                                                        remove(department)
                                                    }
                                                >
                                                    Remove
                                                </Button>
                                            </div>
                                        )}
                                    </>
                                )}
                            </li>
                        ))}
                    </ul>
                </div>

                <p className="mt-3 text-xs text-coffee-800/60">
                    Removing a department leaves its people employed — their
                    record simply shows no department afterwards.
                </p>
            </div>
        </>
    );
}

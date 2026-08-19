import { Head, usePage } from '@inertiajs/react';
import EmployeeController from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import type { DepartmentOption, SalaryTypeOption } from '@/modules/hr';
import { EmployeeForm } from '@/modules/hr';

export default function AddEmployee({
    departments,
    salaryTypes,
}: {
    departments: DepartmentOption[];
    salaryTypes: SalaryTypeOption[];
}) {
    const { currentTeam } = usePage().props;

    return (
        <>
            <Head title="Add Employee" />

            <div className="mx-auto w-full max-w-3xl">
                <h1 className="text-center text-2xl font-bold text-coffee-900">
                    Add Employee
                </h1>

                <EmployeeForm
                    form={EmployeeController.store.form(
                        currentTeam?.slug ?? '',
                    )}
                    departments={departments}
                    salaryTypes={salaryTypes}
                    submitLabel="Add employee"
                    testId="save-employee-button"
                />
            </div>
        </>
    );
}

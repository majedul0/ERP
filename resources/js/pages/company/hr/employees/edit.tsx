import { Head, usePage } from '@inertiajs/react';
import EmployeeController from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import type {
    DepartmentOption,
    EmployeeDetail,
    SalaryTypeOption,
} from '@/modules/hr';
import { EmployeeForm } from '@/modules/hr';

export default function EditEmployee({
    employee,
    departments,
    salaryTypes,
}: {
    employee: EmployeeDetail;
    departments: DepartmentOption[];
    salaryTypes: SalaryTypeOption[];
}) {
    const { currentTeam } = usePage().props;

    return (
        <>
            <Head title={`Edit ${employee.name}`} />

            <div className="mx-auto w-full max-w-3xl">
                <h1 className="text-center text-2xl font-bold text-coffee-900">
                    Edit {employee.name}
                </h1>

                <EmployeeForm
                    form={EmployeeController.update.form({
                        current_team: currentTeam?.slug ?? '',
                        employee: employee.id,
                    })}
                    departments={departments}
                    salaryTypes={salaryTypes}
                    employee={employee}
                    submitLabel="Save changes"
                    testId="save-employee-button"
                />
            </div>
        </>
    );
}

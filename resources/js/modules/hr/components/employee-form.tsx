import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type {
    DepartmentOption,
    EmployeeDetail,
    SalaryTypeOption,
} from '../types';

const selectClasses =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none';

type FormProps = Omit<React.ComponentProps<typeof Form>, 'children'>;

function Field({
    id,
    label,
    children,
    error,
    hint,
    required = false,
}: {
    id: string;
    label: string;
    children: React.ReactNode;
    error?: string;
    hint?: string;
    required?: boolean;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id} className="text-coffee-900">
                {label}
                {required && <span aria-hidden="true">*</span>}
            </Label>
            {children}
            {hint && !error && (
                <p className="text-xs text-coffee-800/60">{hint}</p>
            )}
            <InputError message={error} />
        </div>
    );
}

/**
 * The add/edit form for an employee.
 *
 * Deliberately holds no salary figure. What somebody is paid is effective-dated
 * — a raise in June must not rewrite January's payslip — so it is recorded
 * against a date on the payroll screens rather than typed into the person's
 * details, and it is gated on a different permission besides.
 */
export default function EmployeeForm({
    form,
    departments,
    salaryTypes,
    employee,
    submitLabel,
    testId,
}: {
    form: FormProps;
    departments: DepartmentOption[];
    salaryTypes: SalaryTypeOption[];
    employee?: EmployeeDetail;
    submitLabel: string;
    testId: string;
}) {
    return (
        <Form
            {...form}
            options={{ preserveScroll: true }}
            resetOnSuccess={!employee}
            className="mt-8 space-y-5 pb-16"
        >
            {({ processing, errors: formErrors }) => {
                const errors = formErrors as Record<string, string | undefined>;

                return (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                id="employee_code"
                                label="Staff number"
                                required
                                error={errors.employee_code}
                                hint="Whatever is printed on their ID card."
                            >
                                <Input
                                    id="employee_code"
                                    name="employee_code"
                                    defaultValue={employee?.employeeCode}
                                    required
                                    placeholder="EMP-001"
                                />
                            </Field>

                            <Field
                                id="name"
                                label="Full name"
                                required
                                error={errors.name}
                            >
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={employee?.name}
                                    required
                                />
                            </Field>

                            <Field
                                id="father_name"
                                label="Father's name"
                                error={errors.father_name}
                            >
                                <Input
                                    id="father_name"
                                    name="father_name"
                                    defaultValue={employee?.fatherName ?? ''}
                                />
                            </Field>

                            <Field
                                id="phone"
                                label="Phone"
                                error={errors.phone}
                            >
                                <Input
                                    id="phone"
                                    name="phone"
                                    defaultValue={employee?.phone ?? ''}
                                    placeholder="01XXXXXXXXX"
                                />
                            </Field>

                            <Field id="nid" label="NID" error={errors.nid}>
                                <Input
                                    id="nid"
                                    name="nid"
                                    defaultValue={employee?.nid ?? ''}
                                />
                            </Field>

                            <Field
                                id="designation"
                                label="Designation"
                                error={errors.designation}
                            >
                                <Input
                                    id="designation"
                                    name="designation"
                                    defaultValue={employee?.designation ?? ''}
                                    placeholder="Delivery Supervisor"
                                />
                            </Field>

                            <Field
                                id="department_id"
                                label="Department"
                                error={errors.department_id}
                            >
                                <select
                                    id="department_id"
                                    name="department_id"
                                    className={selectClasses}
                                    defaultValue={employee?.departmentId ?? ''}
                                >
                                    <option value="">None</option>
                                    {departments.map((department) => (
                                        <option
                                            key={department.id}
                                            value={department.id}
                                        >
                                            {department.name}
                                        </option>
                                    ))}
                                </select>
                            </Field>

                            <Field
                                id="salary_type"
                                label="Paid"
                                required
                                error={errors.salary_type}
                                hint="A monthly salary is reduced by absence; a daily wage is earned per day present."
                            >
                                <select
                                    id="salary_type"
                                    name="salary_type"
                                    className={selectClasses}
                                    defaultValue={
                                        employee?.salaryType ??
                                        salaryTypes[0]?.value
                                    }
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
                            </Field>

                            <Field
                                id="joined_on"
                                label="Joining date"
                                required
                                error={errors.joined_on}
                            >
                                <Input
                                    id="joined_on"
                                    name="joined_on"
                                    type="date"
                                    defaultValue={employee?.joinedOn}
                                    required
                                />
                            </Field>

                            <Field
                                id="left_on"
                                label="Leaving date"
                                error={errors.left_on}
                                hint="Leave empty while they still work here. A date here stops payroll counting them from that month."
                            >
                                <Input
                                    id="left_on"
                                    name="left_on"
                                    type="date"
                                    defaultValue={employee?.leftOn ?? ''}
                                />
                            </Field>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                id="address"
                                label="Address"
                                error={errors.address}
                            >
                                <Input
                                    id="address"
                                    name="address"
                                    defaultValue={employee?.address ?? ''}
                                />
                            </Field>

                            <Field
                                id="thana"
                                label="Thana"
                                error={errors.thana}
                            >
                                <Input
                                    id="thana"
                                    name="thana"
                                    defaultValue={employee?.thana ?? ''}
                                />
                            </Field>

                            <Field
                                id="district"
                                label="District"
                                error={errors.district}
                            >
                                <Input
                                    id="district"
                                    name="district"
                                    defaultValue={employee?.district ?? ''}
                                />
                            </Field>

                            <Field
                                id="division"
                                label="Division"
                                error={errors.division}
                            >
                                <Input
                                    id="division"
                                    name="division"
                                    defaultValue={employee?.division ?? ''}
                                />
                            </Field>
                        </div>

                        <Field
                            id="photo"
                            label="Photo"
                            error={errors.photo}
                            hint={
                                employee?.photoUrl
                                    ? 'Choosing a new file replaces the current photo.'
                                    : undefined
                            }
                        >
                            <div className="flex items-center gap-3">
                                {employee?.photoUrl && (
                                    <img
                                        src={employee.photoUrl}
                                        alt=""
                                        className="size-12 rounded-md object-cover"
                                    />
                                )}
                                <Input
                                    id="photo"
                                    name="photo"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                />
                            </div>
                        </Field>

                        <Button
                            type="submit"
                            disabled={processing}
                            data-test={testId}
                            className="bg-coffee-600 hover:bg-coffee-700"
                        >
                            {processing && <Spinner />}
                            {submitLabel}
                        </Button>
                    </>
                );
            }}
        </Form>
    );
}

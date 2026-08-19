<?php

namespace App\Enums;

/**
 * Everything a member can be allowed to do inside a company.
 *
 * Split **view** from **manage** because that is the distinction the business
 * actually makes: a salesperson needs to see products and distributors to raise
 * an invoice, without being able to reprice stock or edit an account.
 *
 * The UI hides what a member cannot do, but hiding is a courtesy — every one of
 * these is enforced on the server by `EnsureTeamPermission`, because a hidden
 * button is still a reachable URL.
 */
enum TeamPermission: string
{
    // Company administration.
    case UpdateTeam = 'team:update';
    case DeleteTeam = 'team:delete';
    case AddMember = 'member:add';
    case UpdateMember = 'member:update';
    case RemoveMember = 'member:remove';
    case CreateInvitation = 'invitation:create';
    case CancelInvitation = 'invitation:cancel';

    // Sales.
    case ViewInvoices = 'invoice:view';
    case CreateInvoice = 'invoice:create';
    case UpdateInvoice = 'invoice:update';
    case DeleteInvoice = 'invoice:delete';

    // Goods sent back. Separate from invoicing because crediting an account is
    // a different act from selling to it — and one worth restricting.
    case ViewReturns = 'return:view';
    case ManageReturns = 'return:manage';

    // Money received.
    case ViewPayments = 'payment:view';
    case ManagePayments = 'payment:manage';

    // Catalogue and customers.
    case ViewProducts = 'product:view';
    case ManageProducts = 'product:manage';
    case ViewDistributors = 'distributor:view';
    case ManageDistributors = 'distributor:manage';

    // Buying.
    case ViewMaterials = 'material:view';
    case ManageMaterials = 'material:manage';
    case ViewVendors = 'vendor:view';
    case ManageVendors = 'vendor:manage';

    // Spending and reporting.
    case ViewExpenses = 'expense:view';
    case ManageExpenses = 'expense:manage';
    case ViewReports = 'report:view';

    /*
     * People.
     *
     * Split three ways rather than two, because who works here, who turned up,
     * and what they are paid are three different questions with three different
     * audiences. A supervisor marking the attendance grid needs `attendance:*`
     * and must not see a salary; `payroll:view` is the one that opens the
     * figures, and every screen and prop that carries money is gated on it
     * rather than on `employee:view`.
     */
    case ViewEmployees = 'employee:view';
    case ManageEmployees = 'employee:manage';
    case ViewAttendance = 'attendance:view';
    case ManageAttendance = 'attendance:manage';
    case ViewPayroll = 'payroll:view';
    case ManagePayroll = 'payroll:manage';

    public function label(): string
    {
        return match ($this) {
            self::UpdateTeam => 'Edit company details',
            self::DeleteTeam => 'Delete the company',
            self::AddMember => 'Add members',
            self::UpdateMember => 'Change roles and permissions',
            self::RemoveMember => 'Remove members',
            self::CreateInvitation => 'Invite people',
            self::CancelInvitation => 'Cancel invitations',

            self::ViewInvoices => 'View invoices',
            self::CreateInvoice => 'Create invoices',
            self::UpdateInvoice => 'Edit invoices and delivery status',
            self::DeleteInvoice => 'Delete invoices',

            self::ViewReturns => 'View sales returns',
            self::ManageReturns => 'Record, edit and delete sales returns',

            self::ViewPayments => 'View payments received',
            self::ManagePayments => 'Record, edit and delete payments',

            self::ViewProducts => 'View products',
            self::ManageProducts => 'Add and edit products',
            self::ViewDistributors => 'View distributors and statements',
            self::ManageDistributors => 'Add, edit and delete distributors',

            self::ViewMaterials => 'View raw materials and stock',
            self::ManageMaterials => 'Add materials and record purchases',
            self::ViewVendors => 'View vendors and their accounts',
            self::ManageVendors => 'Add vendors, bills and payments',

            self::ViewExpenses => 'View expenses',
            self::ManageExpenses => 'Record, edit and delete expenses',
            self::ViewReports => 'View financial reports',

            self::ViewEmployees => 'View employees',
            self::ManageEmployees => 'Add, edit and remove employees',
            self::ViewAttendance => 'View attendance',
            self::ManageAttendance => 'Mark and correct attendance',
            self::ViewPayroll => 'View salaries and payslips',
            self::ManagePayroll => 'Run payroll and record salary payments',
        };
    }

    /**
     * The heading this permission sits under on the settings screen.
     */
    public function group(): string
    {
        return match ($this) {
            self::UpdateTeam, self::DeleteTeam, self::AddMember,
            self::UpdateMember, self::RemoveMember,
            self::CreateInvitation, self::CancelInvitation => 'Company',

            self::ViewInvoices, self::CreateInvoice,
            self::UpdateInvoice, self::DeleteInvoice => 'Sales',

            self::ViewReturns, self::ManageReturns => 'Sales returns',

            self::ViewPayments, self::ManagePayments => 'Payments received',

            self::ViewProducts, self::ManageProducts => 'Products',
            self::ViewDistributors, self::ManageDistributors => 'Distributors',

            self::ViewMaterials, self::ManageMaterials => 'Raw materials',
            self::ViewVendors, self::ManageVendors => 'Vendors',

            self::ViewExpenses, self::ManageExpenses => 'Expenses',
            self::ViewReports => 'Reports',

            self::ViewEmployees, self::ManageEmployees => 'Employees',
            self::ViewAttendance, self::ManageAttendance => 'Attendance',
            self::ViewPayroll, self::ManagePayroll => 'Payroll',
        };
    }

    /**
     * Every permission, grouped, for the settings screen.
     *
     * @return array<int, array{group: string, permissions: array<int, array{value: string, label: string}>}>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::cases() as $permission) {
            $groups[$permission->group()][] = [
                'value' => $permission->value,
                'label' => $permission->label(),
            ];
        }

        return array_map(
            fn (string $group, array $permissions) => [
                'group' => $group,
                'permissions' => $permissions,
            ],
            array_keys($groups),
            $groups,
        );
    }
}

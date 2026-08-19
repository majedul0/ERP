/** One selectable department. */
export type DepartmentOption = {
    id: number;
    name: string;
};

/** A department on its management screen. */
export type DepartmentRow = DepartmentOption & {
    employeeCount: number;
};

/** How somebody is paid — App\Enums\SalaryType. */
export type SalaryTypeOption = {
    value: string;
    label: string;
};

/**
 * A row on the employee list.
 *
 * Carries no money. What somebody is paid sits behind `payroll:view` and the
 * server omits those keys entirely rather than sending them for the page to
 * hide — see EmployeeController::summary().
 */
export type EmployeeRow = {
    id: number;
    employeeCode: string;
    name: string;
    designation: string | null;
    departmentName: string | null;
    phone: string | null;
    salaryType: string;
    salaryTypeLabel: string;
    /** `YYYY-MM-DD`. Employment starts on a day, not at an hour. */
    joinedOn: string;
    /** Null means still employed. */
    leftOn: string | null;
    isActive: boolean;
    photoUrl: string | null;
};

/** Everything the record screen and the edit form need. */
export type EmployeeDetail = EmployeeRow & {
    departmentId: number | null;
    fatherName: string | null;
    nid: string | null;
    address: string | null;
    thana: string | null;
    district: string | null;
    division: string | null;
    fullAddress: string;
};

/** One selectable attendance state — App\Enums\AttendanceStatus. */
export type AttendanceStatusOption = {
    value: string;
    label: string;
    /** The single letter shown in a grid cell. */
    initial: string;
};

/** A row on the attendance grid. */
export type AttendanceEmployee = {
    id: number;
    employeeCode: string;
    name: string;
    departmentName: string | null;
    salaryType: string;
    /** The first day of the month this person may be marked on. */
    firstDay: number;
    /** The last — a leaving date closes the row early. */
    lastDay: number;
};

/** One person's month, counted. */
export type AttendanceSummaryRow = {
    id: number;
    employeeCode: string;
    name: string;
    departmentName: string | null;
    salaryType: string;
    /** Working days they were employed for — the denominator. */
    expectedDays: number;
    present: number;
    halfDays: number;
    paidLeave: number;
    unpaidLeave: number;
    absent: number;
    /**
     * Working days with no mark at all. Normal for salaried staff, who are
     * marked by exception; for daily-wage workers every one of these is a day
     * that will not be paid.
     */
    unmarked: number;
};

export type AttendanceSummaryReport = {
    month: string;
    monthLabel: string;
    workingDays: number;
    rows: AttendanceSummaryRow[];
};

/** A day the company does not work. */
export type HolidayRow = {
    id: number;
    date: string;
    name: string;
};

/** A month of payroll, on the list. */
export type PayrollRunRow = {
    id: number;
    month: string;
    monthLabel: string;
    status: string;
    statusLabel: string;
    employeeCount: number;
    netTotal: number;
    approvedAt: string | null;
};

/** One person's line on a run. */
export type PayrollLineRow = {
    employeeId: number;
    employeeName: string;
    employeeCode: string;
    salaryType: string;
    rateApplied: number;
    unitTotal: number;
    unitPayable: number;
    presentDays: number;
    halfDays: number;
    absentDays: number;
    leaveDays: number;
    grossEarned: number;
    /** The complement of gross, so the row adds up even where it truncated. */
    absenceDeduction: number;
    overtimeHours: number;
    overtimeRate: number;
    overtimeAmount: number;
    bonusAmount: number;
    otherAddition: number;
    otherDeduction: number;
    advanceDeduction: number;
    netPayable: number;
    remarks: string | null;
    /**
     * What has actually been handed over against this run, summed from
     * `salary_payments`. Not a flag on the line — a payment can be corrected or
     * removed after approval, and a stored flag would be another figure to keep
     * in step.
     */
    paid: number;
};

export type PayrollRunDetail = {
    id: number;
    month: string;
    monthLabel: string;
    status: string;
    statusLabel: string;
    approvedAt: string | null;
    approvedBy: string | null;
    note: string | null;
};

/** One payslip, rendered from the run's frozen figures. */
export type Payslip = {
    employeeId: number;
    employeeName: string;
    employeeCode: string;
    designation: string | null;
    departmentName: string | null;
    salaryTypeLabel: string;
    rateApplied: number;
    presentDays: number;
    halfDays: number;
    absentDays: number;
    leaveDays: number;
    grossEarned: number;
    absenceDeduction: number;
    overtimeHours: number;
    overtimeAmount: number;
    bonusAmount: number;
    otherAddition: number;
    otherDeduction: number;
    advanceDeduction: number;
    netPayable: number;
    remarks: string | null;
};

/** Somebody the payment form can pay. */
export type PayableEmployee = {
    id: number;
    name: string;
    employeeCode: string;
    isActive: boolean;
    /** What the company still owes them — negative means they have drawn ahead. */
    balance: number;
};

export type SalaryPaymentRow = {
    id: number;
    employeeId: number;
    employeeName: string;
    employeeCode: string;
    kind: string;
    kindLabel: string;
    paidOn: string;
    amount: number;
    bankName: string | null;
    comment: string | null;
    /** Only set for an advance: how much is still to be recovered. */
    outstanding: number | null;
};

/** An effective-dated rate. */
export type SalaryRateRow = {
    id: number;
    salaryType: string;
    salaryTypeLabel: string;
    amount: number;
    effectiveFrom: string;
};

export type RatedEmployee = {
    id: number;
    name: string;
    employeeCode: string;
    salaryType: string;
    isActive: boolean;
    rates: SalaryRateRow[];
};

export type BonusRow = {
    id: number;
    employeeName: string;
    employeeCode: string;
    bonusType: string;
    bonusTypeLabel: string;
    awardedOn: string;
    amount: number;
    note: string | null;
};

/** One line of an employee's account. */
export type EmployeeLedgerEntry = {
    type: string;
    id: number;
    occurredOn: string;
    reference: string;
    description: string;
    debit: number;
    credit: number;
    balanceAfter: number;
};

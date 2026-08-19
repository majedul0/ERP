/**
 * HR module — the people who work for the company, what they were paid for,
 * and when they turned up.
 *
 * Import from the barrel (`@/modules/hr`), not from files inside it.
 */
export { AttendanceGrid } from './components/attendance-grid';
export type { PendingMark } from './components/attendance-grid';
export { DeleteEmployeeDialog } from './components/delete-employee-dialog';
export { default as EmployeeForm } from './components/employee-form';
export type * from './types';

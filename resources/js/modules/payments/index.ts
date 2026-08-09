/**
 * Payments module — money received from distributors, the banks it lands in,
 * and the running account that explains what is still owed.
 *
 * Import from the barrel (`@/modules/payments`), not from files inside it.
 */
export { default as StatementTable } from './components/statement-table';
export type * from './types';

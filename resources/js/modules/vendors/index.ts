/**
 * Vendors module — who the company buys from, what they have billed, and what
 * has been paid to them.
 *
 * The mirror of distributors on the sales side, and deliberately the same
 * shapes. Import from the barrel (`@/modules/vendors`), not from files inside
 * it.
 */
export { BillForm } from './components/bill-form';
export { VendorForm } from './components/vendor-form';
export { VendorPaymentForm } from './components/vendor-payment-form';
export type * from './types';

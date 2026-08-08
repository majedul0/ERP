/**
 * Invoices module — creating, listing and viewing sales invoices.
 *
 * Import from the barrel (`@/modules/invoices`), not from files inside it.
 */
export { default as DistributorSummary } from './components/distributor-summary';
export { default as InvoiceField } from './components/invoice-field';
export { default as InvoiceLineRows } from './components/invoice-line-rows';
export { default as InvoiceTotals } from './components/invoice-totals';
export { useInvoiceDraft } from './hooks/use-invoice-draft';
export type { InvoiceLineView } from './hooks/use-invoice-draft';
export type * from './types';

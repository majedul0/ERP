/**
 * Documents module — the company's own papers: licences, tax certificates,
 * contracts, and when each of them lapses.
 *
 * Import from the barrel (`@/modules/documents`), not from files inside it.
 */
export { default as DocumentForm } from './components/document-form';
export { formatExpiryDistance, formatFileSize, statusClasses } from './format';
export type * from './types';

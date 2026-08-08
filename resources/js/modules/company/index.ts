/**
 * Company module — the tenant-facing shell: branding, top navigation, layout.
 *
 * Import from the barrel (`@/modules/company`), not from files inside it.
 */
export { default as CompanyHeader } from './components/company-header';
export { default as CompanyLogo } from './components/company-logo';
export { default as WaveBackdrop } from './components/wave-backdrop';
export { useCompanyBrand } from './hooks/use-company-brand';
export { default as CompanyLayout } from './layouts/company-layout';
export { companyNavItems, companyQuickActions } from './nav-items';
export type { CompanyNavItem } from './nav-items';
export type * from './types';

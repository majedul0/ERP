/**
 * Dashboard module — the company landing screen.
 *
 * Import from the barrel (`@/modules/dashboard`), not from files inside it.
 */
export { default as DashboardHero } from './components/dashboard-hero';
export type { QuickAction } from './components/dashboard-hero';
export { default as TodaysSalesTable } from './components/todays-sales-table';
export { useLiveClock } from './hooks/use-live-clock';
export type * from './types';

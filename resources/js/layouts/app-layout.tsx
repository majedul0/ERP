import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { useCompanyTheme } from '@/modules/company';
import type { BreadcrumbItem } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    // The fallback shell wears the company's colour too — see CompanyLayout.
    useCompanyTheme();

    return (
        <AppLayoutTemplate breadcrumbs={breadcrumbs}>
            {children}
        </AppLayoutTemplate>
    );
}

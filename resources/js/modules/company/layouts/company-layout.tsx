import CompanyHeader from '../components/company-header';
import SubscriptionBanner from '../components/subscription-banner';
import { useCompanyBrand } from '../hooks/use-company-brand';

/**
 * Shell for the company-facing (tenant) surface: top navigation instead of the
 * starter kit's sidebar. Settings and team pages keep the sidebar shell.
 */
export default function CompanyLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    const brand = useCompanyBrand();

    return (
        <div className="flex min-h-screen flex-col bg-coffee-50/40">
            <div className="print:hidden">
                <CompanyHeader brand={brand} />
                <SubscriptionBanner />
            </div>
            <main className="mx-auto w-full max-w-[1600px] flex-1 px-4 py-5 lg:px-6">
                {children}
            </main>
        </div>
    );
}

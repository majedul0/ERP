import { dashboard } from '@/routes';
import {
    index as distributorsIndex,
    create as newDistributor,
} from '@/routes/distributors';
import {
    create as newInvoice,
    index as invoicesIndex,
} from '@/routes/invoices';
import {
    index as productsIndex,
    create as newProduct,
} from '@/routes/products';

/**
 * A top-level nav entry, or one row inside a dropdown.
 *
 * `href` is optional: entries for modules that are not built yet render as
 * disabled rows marked "Soon", so the finished information architecture is
 * visible without linking to 404s. Give an entry its Wayfinder route
 * (`@/routes/...`) as its module ships.
 */
export type CompanyNavItem = {
    title: string;
    href?: string;
    items?: CompanyNavItem[];
};

export function companyNavItems(teamSlug: string | null): CompanyNavItem[] {
    if (!teamSlug) {
        return [{ title: 'Dashboard' }];
    }

    return [
        { title: 'Dashboard', href: dashboard(teamSlug).url },
        {
            title: 'Sales',
            items: [
                { title: 'Invoices', href: invoicesIndex(teamSlug).url },
                { title: 'New Invoice', href: newInvoice(teamSlug).url },
                { title: 'Payments Received' },
                { title: 'Sales Returns' },
            ],
        },
        {
            title: 'Products',
            items: [
                { title: 'All Products', href: productsIndex(teamSlug).url },
                { title: 'New Product', href: newProduct(teamSlug).url },
                { title: 'Categories' },
            ],
        },
        {
            title: 'Raw Materials',
            items: [
                { title: 'All Materials' },
                { title: 'Purchases' },
                { title: 'Stock Levels' },
            ],
        },
        { title: 'Distributors', href: distributorsIndex(teamSlug).url },
        {
            title: 'Vendors',
            items: [
                { title: 'All Vendors' },
                { title: 'Vendor Bills' },
                { title: 'Payments Made' },
            ],
        },
        {
            title: 'Finance',
            items: [
                { title: 'Expenses' },
                { title: 'Ledger' },
                { title: 'Reports' },
            ],
        },
    ];
}

/**
 * Quick-action buttons on the dashboard banner.
 */
export function companyQuickActions(
    teamSlug: string | null,
): Array<{ label: string; href?: string }> {
    if (!teamSlug) {
        return [];
    }

    return [
        { label: 'Add Invoice', href: newInvoice(teamSlug).url },
        { label: 'Add Distributor', href: newDistributor(teamSlug).url },
        { label: 'Add Vendor' },
        { label: 'Add Product', href: newProduct(teamSlug).url },
    ];
}

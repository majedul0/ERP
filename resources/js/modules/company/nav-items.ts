import { dashboard } from '@/routes';
import { index as banksIndex } from '@/routes/banks';
import { index as billsIndex } from '@/routes/bills';
import {
    index as distributorsIndex,
    create as newDistributor,
} from '@/routes/distributors';
import { index as expensesIndex } from '@/routes/expenses';
import {
    create as newInvoice,
    index as invoicesIndex,
} from '@/routes/invoices';
import { index as materialsIndex } from '@/routes/materials';
import {
    index as paymentsIndex,
    record as recordPayment,
} from '@/routes/payments';
import {
    index as productsIndex,
    create as newProduct,
} from '@/routes/products';
import { index as purchasesIndex } from '@/routes/purchases';
import { index as reportsIndex } from '@/routes/reports';
import { index as returnsIndex, create as newReturn } from '@/routes/returns';
import { index as stockLevelsIndex } from '@/routes/stock-levels';
import { index as stockReportIndex } from '@/routes/stock-reports';
import { index as vendorPaymentsIndex } from '@/routes/vendor-payments';
import { index as vendorsIndex, create as newVendor } from '@/routes/vendors';

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
    /**
     * Permission values this entry needs; any one of them is enough.
     *
     * Omitted means everyone sees it. An entry the member cannot use is
     * removed rather than disabled — a greyed-out row invites people to ask
     * why, and the answer is never interesting.
     */
    can?: string[];
};

/** Drops entries the member has no permission for, and empty dropdowns with them. */
function permitted(
    items: CompanyNavItem[],
    can: (...permissions: string[]) => boolean,
): CompanyNavItem[] {
    return items
        .filter((item) => !item.can || can(...item.can))
        .map((item) =>
            item.items ? { ...item, items: permitted(item.items, can) } : item,
        )
        .filter((item) => !item.items || item.items.length > 0);
}

export function companyNavItems(
    teamSlug: string | null,
    can: (...permissions: string[]) => boolean = () => true,
): CompanyNavItem[] {
    if (!teamSlug) {
        return [{ title: 'Dashboard' }];
    }

    return permitted(
        [
            { title: 'Dashboard', href: dashboard(teamSlug).url },
            {
                title: 'Sales',
                items: [
                    {
                        title: 'Invoices',
                        href: invoicesIndex(teamSlug).url,
                        can: ['invoice:view'],
                    },
                    {
                        title: 'New Invoice',
                        href: newInvoice(teamSlug).url,
                        can: ['invoice:create'],
                    },
                    {
                        title: 'Payments Received',
                        href: paymentsIndex(teamSlug).url,
                        can: ['payment:view', 'payment:manage'],
                    },
                    {
                        title: 'Sales Returns',
                        href: returnsIndex(teamSlug).url,
                        can: ['return:view', 'return:manage'],
                    },
                    {
                        title: 'New Return',
                        href: newReturn(teamSlug).url,
                        can: ['return:manage'],
                    },
                ],
            },
            {
                title: 'Products',
                items: [
                    {
                        title: 'All Products',
                        href: productsIndex(teamSlug).url,
                        can: ['product:view', 'product:manage'],
                    },
                    {
                        title: 'New Product',
                        href: newProduct(teamSlug).url,
                        can: ['product:manage'],
                    },
                    {
                        title: 'Stock Report',
                        href: stockReportIndex(teamSlug).url,
                        can: ['report:view'],
                    },
                    { title: 'Categories' },
                ],
            },
            {
                title: 'Raw Materials',
                can: ['material:view', 'material:manage'],
                items: [
                    {
                        title: 'All Materials',
                        href: materialsIndex(teamSlug).url,
                    },
                    { title: 'Purchases', href: purchasesIndex(teamSlug).url },
                    {
                        title: 'Stock Levels',
                        href: stockLevelsIndex(teamSlug).url,
                    },
                ],
            },
            {
                title: 'Distributors',
                href: distributorsIndex(teamSlug).url,
                can: ['distributor:view', 'distributor:manage'],
            },
            {
                title: 'Vendors',
                can: ['vendor:view', 'vendor:manage'],
                items: [
                    { title: 'All Vendors', href: vendorsIndex(teamSlug).url },
                    { title: 'Vendor Bills', href: billsIndex(teamSlug).url },
                    {
                        title: 'Payments Made',
                        href: vendorPaymentsIndex(teamSlug).url,
                    },
                ],
            },
            {
                title: 'Finance',
                items: [
                    {
                        title: 'Payments Received',
                        href: paymentsIndex(teamSlug).url,
                        can: ['payment:view', 'payment:manage'],
                    },
                    {
                        title: 'Banks',
                        href: banksIndex(teamSlug).url,
                        can: [
                            'payment:manage',
                            'expense:manage',
                            'vendor:manage',
                        ],
                    },
                    {
                        title: 'Expenses',
                        href: expensesIndex(teamSlug).url,
                        can: ['expense:view', 'expense:manage'],
                    },
                    {
                        title: 'Reports',
                        href: reportsIndex(teamSlug).url,
                        can: ['report:view'],
                    },
                ],
            },
        ],
        can,
    );
}

/**
 * Quick-action buttons on the dashboard banner.
 */
export function companyQuickActions(
    teamSlug: string | null,
    can: (...permissions: string[]) => boolean = () => true,
): Array<{ label: string; href?: string }> {
    if (!teamSlug) {
        return [];
    }

    return [
        {
            label: 'Add Invoice',
            href: newInvoice(teamSlug).url,
            can: ['invoice:create'],
        },
        {
            label: 'Add Payment',
            href: recordPayment(teamSlug).url,
            can: ['payment:manage'],
        },
        {
            label: 'Add Distributor',
            href: newDistributor(teamSlug).url,
            can: ['distributor:manage'],
        },
        {
            label: 'Add Vendor',
            href: newVendor(teamSlug).url,
            can: ['vendor:manage'],
        },
        {
            label: 'Add Product',
            href: newProduct(teamSlug).url,
            can: ['product:manage'],
        },
    ]
        .filter((action) => can(...action.can))
        .map(({ label, href }) => ({ label, href }));
}

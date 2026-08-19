/** One recorded expense. */
export type Expense = {
    id: number;
    category: string;
    categoryLabel: string;
    description: string;
    /** `YYYY-MM-DD`. Money is spent on a day. */
    spentOn: string;
    amount: number;
    bankId: number | null;
    bankName: string | null;
    note: string | null;
};

/**
 * A wage shown on the expenses screen.
 *
 * Read from `salary_payments`, never copied into `expenses` — which is why it
 * has no id you can edit against and no category value, only a label.
 */
export type WageRow = {
    id: number;
    categoryLabel: string;
    description: string;
    spentOn: string;
    amount: number;
    bankName: string | null;
};

/** One selectable category, from App\Enums\ExpenseCategory. */
export type ExpenseCategoryOption = {
    value: string;
    label: string;
};

/** One category's share of a period's spending. */
export type ExpenseCategoryTotal = {
    category: string;
    label: string;
    amount: number;
};

/** One point on the trend chart: a month, or a whole year. */
export type AnalyticsBucket = {
    /** `2026-08` monthly, `2026` yearly. */
    key: string;
    /** `Aug` or `2026` — what goes under the axis. */
    label: string;
    revenue: number;
    /** Operating expenses plus what vendors billed over the same stretch. */
    expenses: number;
    /**
     * Revenue less expenses. An operating result, not profit — the invoice
     * never recorded what a product cost to make. See App\Support\FinancialAnalytics.
     */
    net: number;
};

/** The trend band above the report. */
export type FinancialAnalytics = {
    granularity: 'monthly' | 'yearly';
    period: { from: string; to: string; label: string };
    buckets: AnalyticsBucket[];
    totals: { revenue: number; expenses: number; net: number };
    /** At most six slices; the tail is folded into "Other categories". */
    expenseBreakdown: ExpenseCategoryTotal[];
};

/** The financial report for a period. */
export type FinancialReport = {
    period: { from: string; to: string };
    sales: {
        invoiceCount: number;
        gross: number;
        discounts: number;
        schemes: number;
        /** Goods sent back during the period, whenever they were sold. */
        returns: number;
        net: number;
    };
    money: {
        received: number;
        expenses: number;
        vendorPaid: number;
        /**
         * Wages, from `salary_payments` — the one table they leave by. Its own
         * figure rather than folded into `expenses`, so the money card adds up
         * and the difference between what came in and what is left has a name.
         */
        salaryPaid: number;
        materialPurchases: number;
        vendorBilled: number;
        netCash: number;
    };
    expensesByCategory: ExpenseCategoryTotal[];
    /** Balances as of today — deliberately not filtered by the period. */
    standing: {
        receivable: number;
        payable: number;
        materialStockValue: number;
    };
};

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

/** One selectable category, from App\Enums\ExpenseCategory. */
export type ExpenseCategoryOption = {
    value: string;
    label: string;
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
        materialPurchases: number;
        vendorBilled: number;
        netCash: number;
    };
    expensesByCategory: Array<{
        category: string;
        label: string;
        amount: number;
    }>;
    /** Balances as of today — deliberately not filtered by the period. */
    standing: {
        receivable: number;
        payable: number;
        materialStockValue: number;
    };
};
